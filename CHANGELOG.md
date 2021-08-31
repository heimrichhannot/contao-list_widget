# Changelog
All notable changes to this project will be documented in this file.

## [2.2.0] - 2021-08-31

- Added: support for php 8

## [2.1.7] - 2018-02-01

### Fixed 
- ensure that `$arrOptions['column']` is not empty

## [2.1.6] - 2018-02-01

### Fixed 
- `ListWidget::filterSQL` too few arguments when no `where` condition set

## [2.1.5] - 2018-02-01

### Fixed 
- `ListWidget::filterSQL` did overwrite `column` options if they were no array -> default `AND` filter criteria will get lost

### Changed
- Licence from LGPL-3.0+ to LGPL-3.0-or-later

## [2.1.4] - 2017-09-22

### Fixed 
* methods not set static in list widget (error in contao 4)

## [2.1.3] - 2017-06-30

### Fixed
- search within datatable ignored previous where filter

## [2.1.2] - 2017-06-26

### Added
- support for contao 4 and php 7

## [2.1.1] - 2017-03-13

### Fixed
- header_fields_callback, fixed arguments order

## [2.1.0] - 2017-03-07

### Fixed
- refactoring of config options
- readme

## [2.0.0] - 2017-03-06

### Added
- support for ajax reload
