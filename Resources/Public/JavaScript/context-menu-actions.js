// Context menu callback for the "Edit image" entry. The context menu imports
// this module (data-callback-module) and calls the static action named by
// `callbackAction` with (table, uid, dataset). `uid` is the combined identifier.
class ImageEditorContextMenuActions {
  static editImage(table, uid, dataset) {
    const listFrame = top.list_frame;
    const returnUrl = listFrame
      ? encodeURIComponent(listFrame.document.location.pathname + listFrame.document.location.search)
      : '';
    const url = dataset.actionUrl
      + '&target=' + encodeURIComponent(uid)
      + '&returnUrl=' + returnUrl;
    top.TYPO3.Backend.ContentContainer.setUrl(url);
  }
}

export default ImageEditorContextMenuActions;
