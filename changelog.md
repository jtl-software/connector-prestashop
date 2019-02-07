1.5.6
------
- CO-432 - Added support for purchase-price (wholesaleprice)

1.5.5
------
- Improved error messages
- Updated deprecated functions
- CO-360 - Added missing translations 
- CO-358 - Added an feature to delete inconsistent specific data through the module configuration 

1.5.4
------
- Added Connector Plugin support
- Added support for product specifics 
- Varcombi child attributes are now ignored because they are not supported. (And are now no longer removing varcombi father attributes)

1.5.3
------
- Fixed out of stock feature 
- CO-311 - Filled id_category_default with the first category that is sent.
- Added new build mechanism
- Extend build.xml

1.5.2
------
- Changed to new version management
- Added PriceGross on ShippingOrderItem
- Removed unnecessary exceptions

1.5.1
------
- Set salutation only in case a known id_gender value exists

1.4.1
------
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

1.4
------
- [5bc2177]
  added special price push

- [9f15251]
  added additional requirement checks on module install

1.3
------
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

1.2
------

1.1
------
- [841f053]
  fixed product attributes

- [a31ef49]
  updated changelog.md

1.0.9
------
- [edd0881]
  fixed product attr i18ns
  fixed shipping price quantity
  cleaned up old code

- [7ab567f]
  updated changelog

1.0.8
------
- [2c0ddfc]
  raised connector version

- [e66193c]
  changelog update

1.0.7
------
- [5f24bc6]
  removed direct function calls on getters
  fixed method definitions

- [bbaab76]
  added changelog

1.0.6
------
- [43abfb5]
  added writable-check on module install

