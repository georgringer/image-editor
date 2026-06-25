// esbuild entry: expose the vanilla Filerobot constructor as a browser global.
// The vanilla `filerobot-image-editor` wrapper embeds React internally, so our
// own glue code (editor.js) stays React-free and just calls
// `new window.FilerobotImageEditor(container, config).render(...)`.
import FilerobotImageEditor from 'filerobot-image-editor';

window.FilerobotImageEditor = FilerobotImageEditor;
