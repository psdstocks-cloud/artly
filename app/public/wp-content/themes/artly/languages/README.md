# Artly Theme Translation Files

This directory contains translation files for the Artly bilingual homepage.

## Files

- `artly.pot` - Translation template (source file)
- `artly-en_US.po` - English translations
- `artly-ar.po` - Arabic translations

## Compiling Translation Files

WordPress requires `.mo` (Machine Object) files to load translations. You need to compile the `.po` files to `.mo` files.

### Option 1: Using Poedit (Recommended)

1. Download and install [Poedit](https://poedit.net/)
2. Open `artly-en_US.po` in Poedit
3. Click "Save" - Poedit will automatically generate `artly-en_US.mo`
4. Repeat for `artly-ar.po`

### Option 2: Using WP-CLI

If you have WP-CLI installed:

```bash
cd wp-content/themes/artly/languages
msgfmt artly-en_US.po -o artly-en_US.mo
msgfmt artly-ar.po -o artly-ar.mo
```

### Option 3: Using msgfmt (Linux/Mac)

If you have `gettext` tools installed:

```bash
msgfmt artly-en_US.po -o artly-en_US.mo
msgfmt artly-ar.po -o artly-ar.mo
```

## Updating Translations

1. Edit the `.po` files with a text editor or Poedit
2. Recompile to `.mo` files
3. Clear WordPress cache if using a caching plugin

## Notes

- Arabic translations should be reviewed by a native Arabic speaker
- The `.mo` files are binary and should not be edited directly
- Always keep `.po` and `.mo` files in sync

