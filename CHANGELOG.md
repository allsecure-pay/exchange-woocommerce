# Release Notes

## v2.0.1 (2023-06-15)
### Fixed
- Seamless form compatibility tested with a number of templates

## v2.0.0 (2023-06-01)
### Fixed
- Upgraded version to support most recent WooCommerce functionalities

## v1.9.10 (2022-08-24)
### Fixed
- Success message to email receipt only when allsecure method
- Display of decline message when syncronous transaction
- Minor translation changes
###Added 
- Additional details when transaction declined

## v1.9.9 (2022-08-08)
### Fixed
- Minor translation changes
###Added 
- success message to email receipt

## v1.9.8 (2022-04-01)
### Fixed
- DinaCard Logo updated
- VISA Logo updated
- Responsive screen improved
### Added
- Payment.js v 1.3 implemented

## v1.9.7 (2021-10-15)
### Fixed
- Amex Logo (small scale) added
- VISA Logo updated

## v1.9.6 (2021-08-15)
### Added
- Show response code when config error

## v1.9.5 (2021-08-05)
### Fixed
- Responsive CSS display

## v1.9.4 (2021-08-02)
### Fixed
- Footer banner display

## v1.9.3 (2021-06-08)
### Fixed
- Approval code for NLB bank

## v1.9.2 (2021-02-25)
### Fixed
- Fixed compliance issues with individual banks

## v1.9.1 (2021-02-05)
### Fixed
- Seamless form compatibility tested with a number of templates

## v1.9.0 (2021-01-21)
### Added
- Additional Banking Partners 
- Upgraded Seamless form display 

## v1.8.0 (2020-09-21)
### Added
- Backoffice Capture Function 
- Backoffice Reversal Function 
## v1.7.8 (2020-09-01)
### Added
- Backoffice Capture Function (Beta Testing)
- Backoffice Reversal Function (Beta Testing)
## Fixed
- Seamless form display

## v1.7.7 (2020-05-08)
## Fixed
- AuthCode display

## v1.7.6 (2020-04-24)
### Added
- Seamless integration method upgraded
- Languages fix

## v1.7.5 (2020-03-29)
### Added
- Card type to script handle for js asset
- Custom Credit Card Icons on a checkout page
- Ongoing AuthCode implementation
- General merchants info to comply with VISA requirements
### Fixed
- Footer Fix

## v1.7.4 (2020-01-30)
### Added
- Footer banner added including a backend configuration
- Version checker added

### Fixed
- Callback missing OK response

## v1.7.3 (2020-01-10)
### Added
- Bosnian Translation

## v1.7.2 (2019-12-29)
### Added
- Test / Live Host configuration

## v1.7.1 (2019-11-21)
### Added
- Added error translation 

## v1.7.0 (2019-11-19)
### Added
- Explicit check of callback
- Clear cart filter
- Source platform header

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
