# Release Notes

## Unreleased

## v1.6.1 (2019-10-21)
### Fixed
- Do not force http on callback URL

## v1.6.0 (2019-10-18)
### Changed
- Error return url redirect to checkout page with error message
- Set payment status more explicitly
- Unique order IDs in transaction
- Handle Void/Capture postback

## v1.5.0 (2019-10-17)
### Changed
- Remove redundant transaction request option read
- Remove incorrect payment complete call on seamless finish
- Hide all payment gateways except selected gateway within order
### Fixed
- Seamless checkout sends incorrect token if more than one seamless payment option is available
- Gateway client 7.3 compatibility: remove redundant filter_var FILTER_VALIDATE_URL flags

## v1.4.2 (2019-10-16)
### Fixed
- Decode HTML entities in stored password option within callbacks as well
- Explicitly re-read transaction request type option

## v1.4.0 (2019-10-16)
### Changed
- Display error to user on any payment errors
### Fixed
- Decode HTML entities in stored password option 

## v1.3.1 (2019-10-05)
### Added
- Support for multi language

## v1.3.0 (2019-09-30)
### Added
- Preauthorize/Capture/Void transaction request option
- Plugin author, WooCommerce minimum & tested up to version
### Changed
- Unified payment failure response

## v1.2.0 (2019-09-10)
### Added
- [README](README.md) note on enabling/disabling additional adapters
- Enable seamless integration by setting integration key

## v1.1.0 (2019-09-03)
### Added
- Configuration option for API host per card
- 3D Secure 2.0 extra data
### Changed
- API Password internal option name (has to be set again)
### Fixed
- Use `wc_get_checkout_url()` instead of deprecated `WC_Cart::get_checkout_url()`.

## v1.0.0 (2019-08-29)
### Added
- Build script and [README](README.md) with instructions
- [CHANGELOG](CHANGELOG.md)
### Changed
- Moved renamed source to `src`

## 2019-07-05
### Added
- Module & payment extension
- Credit card payment with redirect flow
- Configuration values for card types
