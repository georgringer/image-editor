import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import { html } from 'lit';
import { createRef, ref } from 'lit/directives/ref.js';

// Initialises the bundled Filerobot editor on the dedicated editor page,
// suppresses Filerobot's built-in save modal and routes saving through our own
// dialog + backend endpoint. On a name collision the user picks overwrite or
// rename (mirroring the core upload conflict flow); "skip" makes no sense here.
class ImageEditor {
  constructor() {
    this.el = document.getElementById('image-editor');
    if (!this.el) {
      return;
    }
    if (typeof window.FilerobotImageEditor !== 'function') {
      console.error('[image_editor] Filerobot bundle not available.');
      this.el.textContent = 'The image editor could not be loaded.';
      return;
    }

    this.returnUrl = this.el.dataset.returnUrl ? decodeURIComponent(this.el.dataset.returnUrl) : '';
    this.target = this.el.dataset.target;
    this.folder = this.el.dataset.folder;
    this.filename = this.el.dataset.filename || 'image';
    this.extension = (this.el.dataset.extension || '').toLowerCase();
    this.saveUrl = TYPO3.settings.ajaxUrls['image_editor_save'];
    this.fileExistsUrl = TYPO3.settings.ajaxUrls['file_exists'];

    const FIE = window.FilerobotImageEditor;
    const { TABS, TOOLS } = FIE;
    const config = this.readConfig();
    const tabsIds = this.resolveTabs(config, TABS);
    const cropPresets = this.resolveCropPresets(config);
    // Localized labels for our own TYPO3 dialogs (built in PHP from locallang.xlf).
    this.labels = config.labels || {};

    const fieConfig = {
      source: this.el.dataset.source,
      defaultSavedImageName: this.stripExtension(this.filename),
      // Filerobot's own tool labels, resolved per backend-user language in PHP.
      useBackendTranslations: false,
      language: config.language || 'en',
      translations: config.translations || undefined,
      tabsIds,
      defaultTabId: tabsIds[0],
      defaultToolId: tabsIds.includes(TABS.ADJUST) ? TOOLS.CROP : undefined,
      // The source is streamed extension-less, so Filerobot cannot infer the
      // original type and would otherwise default to PNG. Forcing the export
      // type to match the original extension keeps the saved bytes consistent
      // with the file extension (TYPO3 v14 rejects mismatches).
      defaultSavedImageType: this.savedImageType(),
      // Suppress Filerobot's own save modal; onSave still fires with the result.
      onBeforeSave: () => false,
      onSave: (imageData) => this.openSaveDialog(imageData),
      onClose: () => this.close(),
    };

    // Configured presets are appended to Filerobot's built-in crop presets.
    if (cropPresets.length) {
      fieConfig[TOOLS.CROP] = { presetsItems: cropPresets };
    }

    this.editor = new FIE(this.el, fieConfig);
    this.editor.render({ onClose: () => this.close() });
  }

  readConfig() {
    try {
      return JSON.parse(this.el.dataset.config || '{}');
    } catch (e) {
      return {};
    }
  }

  // Resolves a localized dialog label by its locallang.xlf id, falling back to
  // the given English default when the label is missing.
  label(key, fallback) {
    const value = this.labels[key];
    return typeof value === 'string' && value !== '' ? value : fallback;
  }

  // Maps the configured tab tokens (options.imageEditor.tabs) to Filerobot tab ids.
  resolveTabs(config, TABS) {
    const map = {
      adjust: TABS.ADJUST,
      finetune: TABS.FINETUNE,
      filters: TABS.FILTERS,
      annotate: TABS.ANNOTATE,
      resize: TABS.RESIZE,
      watermark: TABS.WATERMARK,
    };

    const ids = (config.tabs || [])
      .map((token) => map[String(token).toLowerCase()])
      .filter((id) => typeof id !== 'undefined');

    return ids.length ? ids : [TABS.ADJUST, TABS.FINETUNE, TABS.FILTERS, TABS.ANNOTATE, TABS.RESIZE];
  }

  // Builds extra crop presets from options.imageEditor.cropPresets.
  // Tokens: "W:H" (e.g. 21:9) or "Label=W:H" (e.g. Cinemascope=21:9).
  resolveCropPresets(config) {
    return (config.cropPresets || [])
      .map((token) => this.cropPreset(String(token)))
      .filter((preset) => preset !== null);
  }

  cropPreset(raw) {
    const token = raw.trim();
    const separator = token.indexOf('=');
    const label = separator === -1 ? token : token.slice(0, separator).trim();
    const ratioPart = separator === -1 ? token : token.slice(separator + 1).trim();

    const match = ratioPart.match(/^(\d+):(\d+)$/);
    if (!match) {
      return null;
    }
    const width = parseInt(match[1], 10);
    const height = parseInt(match[2], 10);
    if (width <= 0 || height <= 0) {
      return null;
    }

    return { titleKey: label || ratioPart, ratio: width / height };
  }

  stripExtension(name) {
    return name.replace(/\.[^.]+$/, '');
  }

  // Maps the original file extension to the Filerobot export type so the saved
  // bytes match the file extension. The editor only opens jpg/jpeg/png/webp.
  savedImageType() {
    const map = { jpg: 'jpeg', jpeg: 'jpeg', png: 'png', webp: 'webp' };
    return map[this.extension] || 'png';
  }

  close() {
    if (this.editor) {
      this.editor.terminate();
    }
    if (this.returnUrl) {
      window.location.href = this.returnUrl;
    }
  }

  openSaveDialog(imageData) {
    const defaultName = this.stripExtension(this.filename);
    const inputRef = createRef();

    // A non-string content makes Modal render it as a lit template (type=template).
    const content = html`
      <div class="form-group">
        <label class="form-label" for="image-editor-filename"
          >${this.label('save.dialog.filename', 'File name')}</label
        >
        <input
          ${ref(inputRef)}
          type="text"
          class="form-control"
          id="image-editor-filename"
          .value=${defaultName}
        />
      </div>`;

    Modal.advanced({
      title: this.label('save.dialog.title', 'Save a copy'),
      content,
      severity: SeverityEnum.notice,
      buttons: [
        {
          text: this.label('save.dialog.cancel', 'Cancel'),
          btnClass: 'btn-default',
          name: 'cancel',
          trigger: (event, modal) => modal.hideModal(),
        },
        {
          text: this.label('save.dialog.submit', 'Save copy'),
          btnClass: 'btn-primary',
          name: 'save',
          active: true,
          trigger: (event, modal) => {
            const name = (inputRef.value?.value ?? '').trim() || defaultName;
            modal.hideModal();
            this.checkAndSave(name, imageData.imageBase64);
          },
        },
      ],
    });
  }

  checkAndSave(name, imageBase64) {
    const fullName = name + '.' + this.extension;
    new AjaxRequest(this.fileExistsUrl)
      .withQueryArguments({ fileName: fullName, fileTarget: this.folder })
      .get({ cache: 'no-cache' })
      .then(async (response) => {
        const existing = await response.resolve();
        if (existing && typeof existing.uid !== 'undefined') {
          this.openConflictDialog(name, fullName, imageBase64);
        } else {
          this.persist(name, 'copy', imageBase64);
        }
      })
      .catch(() => {
        // If the existence check fails, fall back to a non-destructive copy.
        this.persist(name, 'copy', imageBase64);
      });
  }

  openConflictDialog(name, fullName, imageBase64) {
    // The message carries a "%s" placeholder for the file name; split around it
    // so the name can be rendered bold inside the lit template.
    const messageParts = this.label('conflict.message', 'A file named %s already exists in this folder.').split('%s');
    const content = html`
      <p>${messageParts[0]}<strong>${fullName}</strong>${messageParts[1] ?? ''}</p>
      <p>${this.label('conflict.hint', 'Overwrite it, or save the edited image under a new (renamed) file?')}</p>`;

    Modal.advanced({
      title: this.label('conflict.title', 'File already exists'),
      content,
      severity: SeverityEnum.warning,
      buttons: [
        {
          text: this.label('save.dialog.cancel', 'Cancel'),
          btnClass: 'btn-default',
          name: 'cancel',
          trigger: (event, modal) => modal.hideModal(),
        },
        {
          text: this.label('conflict.rename', 'Rename'),
          btnClass: 'btn-default',
          name: 'rename',
          active: true,
          trigger: (event, modal) => {
            modal.hideModal();
            this.persist(name, 'copy', imageBase64);
          },
        },
        {
          text: this.label('conflict.overwrite', 'Overwrite'),
          btnClass: 'btn-warning',
          name: 'overwrite',
          trigger: (event, modal) => {
            modal.hideModal();
            this.persist(name, 'overwrite', imageBase64);
          },
        },
      ],
    });
  }

  persist(name, mode, imageBase64) {
    new AjaxRequest(this.saveUrl)
      .post({
        target: this.target,
        mode,
        filename: name,
        image: imageBase64,
      })
      .then(async (response) => {
        const data = await response.resolve();
        if (data.success) {
          Notification.success(this.label('save.success', 'Image saved'), data.file.name, 3);
          if (this.returnUrl) {
            window.location.href = this.returnUrl;
          }
        } else {
          Notification.error(this.label('save.error', 'Could not save image'), data.message || '');
        }
      })
      .catch(() => {
        Notification.error(
          this.label('save.error', 'Could not save image'),
          this.label('save.error.unexpected', 'An unexpected error occurred.'),
        );
      });
  }
}

export default new ImageEditor();
