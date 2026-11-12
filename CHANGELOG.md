# Changelog

## Unreleased

### Fixed

- A kit from a path repository, which Composer links instead of copying, no longer gets a `.composer-kit` written into its own source.

## 0.2.0 (2026-11-12)

### Added

- Site kits: a package of type `cms-kit` lands in the site's `kits/` folder, `kits/<name>` for ours and `kits/<vendor>-<name>` for a third party's, where the CMS finds it to apply. Its folder name must be a kit handle (lowercase letters, digits, `-` and `_`), and Composer never empties a `kits/` folder it didn't put there itself: it marks its own with `.composer-kit`.

## 0.1.0 (2026-11-01)

### Added

- The installer for plugins and the site's root files.
