# 🖼️ Image Editor for TYPO3

Edit images directly from the TYPO3 backend file list using the open-source
[Filerobot Image Editor](https://github.com/scaleflex/filerobot-image-editor).

[![Watch the demo](https://img.youtube.com/vi/M8iOw8A41dg/maxresdefault.jpg)](https://youtu.be/M8iOw8A41dg)

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
