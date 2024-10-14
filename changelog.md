## unreleased
- CO-2508 - Use wawi tracking lists

## 2.0.1 _2024-07-05_
- Fixed type errors

## 2.0.0 _2024-06-19_
- CO-2404 Update to Connector Core 5.2
  This update is no longer compatible with old Connector Plugins

## 1.13.4 _2023-08-08_
- CO-2373 - Fix database exception when rrp > net price

## 1.13.3 _2023-04-11_
- CO-2323 - Fixed wrong Presta Version in Wawi License check
- CO-2291 - Fix possible sql injections

## 1.13.2 _2022-11-08_
- CO-2103 - duplicate products on invalid characters

## 1.13.1  _2022-05-20_
- CO-2018 - Hotfix for product feature query

## 1.13.0 _2022-05-19_
- CO-1999 - Added support for watermark generation
- CO-1883 - Added support for product shipping carriers

## 1.12.1 _2022-03-15_
- Added missing product type on customer order line item
- CO-1951 - Fixed update procedure

## 1.12.0 _2022-02-01_
- CO-1881 - Added saving manufacturer part number in shop
- CO-1216 - Added recommended retail price as attribute in shop
- CO-1839 - Enabled updating customer data in shop
- CO-1877 - Fixed importing customer data
- CO-1804 - Fixed tax rate value when importing coupons in customer order
- CO-1776 - Fixed import articles with inactive languages

## 1.11.0 _2021-07-05_
- CO-1340 - Fixed multiple creation of images
- CO-1244 - Feature replaced connector.phar by lib/ folder
- Fixed table prefix in order pull

## 1.10.0 _2021-06-23_
- CO-314  - Added config option to decide if unknown product attributes should get deleted or not
- CO-1224 - Fixed problem with importing splitted orders
- CO-1463 - Added product tax class guessing on product push
- CO-1500 - Added support for not overriding features.json file on connector update
- CO-1525 - Added product tags handling support by using "tags" attribute

## 1.9.0 _2021-04-13_
- Increased minimum PHP version to 7.1.3, updated connector core to version 3 
- CO-1450 - Feature do not allow saving duplicated tracking codes
- CO-1395 - Feature added payment mappings
- CO-1309 - Feature added attribute 'delivery_out_stock' to control delivery time that are not in stock
- CO-1096 - Fixed use language code instead of country code 
- CO-817 - Fixed find tax rule for non german rates
- CO-418 - Added support for radio and select variation types

## 1.8.0 _2021-03-03_
- CO-1324 - Fixed image assignments for variations are deleted 
- CO-1064 - Improved base price handling
- CO-1050 - Transfer customer order messages in CustomerOrder notes

## 1.7.3 _2020-12-01_
- CO-1219 - Allow for save html or just iframe in product and category description (needs to be enabled in presta backend)

## 1.7.2 _2020-11-18_
- CO-1163 Fixed cover image not set
- CO-621 Fixed setting shipment tracking id

## 1.7.1 _2020-08-17_
- Fixed problem with not existing main category id during product import in JTL-Wawi

## 1.7.0 _2020-08-04_
- CO-854 - Added main category support by product attribute "main_category_id" (Category id from JTL-Wawi [kKategorie] has to be set)
- CO-974 - Added fix that payments can be pulled only when related order was pulled first into JTL-Wawi
- CO-1042 - Added importing product image titles as alt text
- CO-1044 - Fixed problem with translations from deactivated shop languages

## 1.6.3.1 _2020-06-15_
- CO-1003 - Fixed image alt text wasn't saved on push
- CO-864 - Added improvements in tax calculations

## 1.6.3 _2020-03-24_
- CO-845 - Add sort to product attributes
- CO-802 - Special prices fix

## 1.6.2.1
- CO-700 - Products with an endpoint_id of 0 are now ignored on stats and pull.
- Fixed the Delete call of the primarykeymapper

## 1.6.2
- CO-620 - Fixed division by zero error on CustomerOrder Pull (price = 0)
- CO-585 - Fixed default currency detection
- CO-604 - Fixed connector linking table collation on install/update
- CO-448 - Added Integrationtests
- CO-575 - Attributes are no longer pulled as Specifics
- CO-579 - Added ISBN, keywords, variation->values-> ean, stocklevel and sku are now filled on product pull
- CO-587 - Added Product price will now be set on the product itself instead of only in customer group prices
- CO-635 - Fixed ProductPrice push, previously failing on updating multiple prices at once

## 1.6.1
- Linking table access is now fixed
- Fixed image push (Error on setting the cover)

## 1.5.9
- CO-489 - Corrected Taxes for orders
- CO-366 - The linking table was split into seperate tables to improve performance
- CO-521 - Fixed a bug regarding cover images
- CO-530 - Implemented product special attributes like "products_status"

## 1.5.8.1
- Fixed a bug on ProductAttr push
- CO-489 - Corrected Taxes for order items

## 1.5.8
- CO-461 - Article prices that are too precise should now be rounded to 4 decimals
- CO-460 - Fixed Category-Parent for import
- CO-459 - Fixed Log-Downloading
- CO-458 - Products without consider_stock can now be bought
- CO-456 - Creation of duplicates should now be prevented

## 1.5.7
- CO-388 - Reworked the configuration page
- CO-390 - Refactoring to reduce logs
- CO-322 - Added Coupon handling

## 1.5.6
- CO-432 - Added support for purchase-price (wholesaleprice)

## 1.5.5
- Improved error messages
- Updated deprecated functions
- CO-360 - Added missing translations 
- CO-358 - Added an feature to delete inconsistent specific data through the module configuration 

## 1.5.4
- Added Connector Plugin support
- Added support for product specifics 
- Varcombi child attributes are now ignored because they are not supported. (And are now no longer removing varcombi father attributes)

## 1.5.3
- Fixed out of stock feature 
- CO-311 - Filled id_category_default with the first category that is sent.
- Added new build mechanism
- Extend build.xml

## 1.5.2
- Changed to new version management
- Added PriceGross on ShippingOrderItem
- Removed unnecessary exceptions

## 1.5.1
- Set salutation only in case a known id_gender value exists

## 1.4.1
- [fb8bc5b]
  bugfix for presta cover images

- [da36d83]
  added gross prices

- [54eda2d]
  changes to comply presta module validation requirements

- [0edc6f9]
  added module key for presta market

- [24b843a]
  bugfix for variation price
  add link table index checks

- [f610bd0]
  added order attribute for gift and gift message

## 1.4
- [5bc2177]
  added special price push

- [9f15251]
  added additional requirement checks on module install

## 1.3
- [d86b933]
  fixed tax rule selection on push

- [d28c9f3]
  check and set default varcombi on push
  set min quantity to 1 instead of 0

- [81b51e0]
  fixed permitNegativeStock

- [899b727]
  fixed tax group query

- [9014a86]
  workaround for invalid creationDate

- [098537b]
  updated changelog

- [c34590e]
  fixed db prefixes

## 1.2

## 1.1
- [841f053]
  fixed product attributes

- [a31ef49]
  updated changelog.md

## 1.0.9
- [edd0881]
  fixed product attr i18ns
  fixed shipping price quantity
  cleaned up old code

- [7ab567f]
  updated changelog

## 1.0.8
- [2c0ddfc]
  raised connector version

- [e66193c]
  changelog update

## 1.0.7
- [5f24bc6]
  removed direct function calls on getters
  fixed method definitions

- [bbaab76]
  added changelog

## 1.0.6
- [43abfb5]
  added writable-check on module install

