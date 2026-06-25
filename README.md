# 🖼️ Image Editor for TYPO3

Edit images directly from the TYPO3 backend file list using the open-source
[Filerobot Image Editor](https://github.com/scaleflex/filerobot-image-editor).
xx
<video src="https://www.youtube.com/watch?v=M8iOw8A41dg&feature=youtu.be" width="80%" controls></video>
xx
<video src="https://www.youtube.com/watch?v=M8iOw8A41dg&feature=youtu.be" width="320" height="240" controls></video>

or

<iframe width="560" height="315" src="[https://www.youtube.com/embed/video-id](https://youtu.be/M8iOw8A41dg)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

## 📦 Setup

Install with composer `georgringer/image-editor` or download from TER.

## ✅ Requirements

- TYPO3 13 LTS, 14 LTS

## ⚙️ Configuration

The extension can be configured by UserTsConfig

```typo3_typoscript
# Enabled by default, but can be disabled for specific editors & groups
options.imageEditor.enable = 0

# Configure the available editor tabs
options.imageEditor.tabs = adjust,finetune,filters,annotate,resize

# Enable **additional image crop presets, include an optional label
options.imageEditor.cropPresets = 21:9, 5:4, Cinemascope=21:9, Story=9:16
```

## 🛠️ Development

```bash
composer build:install
composer build:update
```
