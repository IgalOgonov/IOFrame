<?php
//Definitions, and helper functions
/* AUTH */
CONST CONTACTS_GET = 'CONTACTS_GET';
CONST CONTACTS_MODIFY = 'CONTACTS_MODIFY';

/* Input */
CONST REGEX_REGEX = '^[\w\-\.\_ ]{1,128}$';
CONST CONTACT_TYPE_REGEX = '^[a-zA-Z]\w{0,63}$';
CONST IDENTIFIER_REGEX = '^[\w ]{1,256}$';
CONST NAME_REGEX = '^[a-zA-Z][a-zA-Z \-\.]{1,63}$';
CONST PHONE_REGEX = '^\+?\d{6,20}$';
CONST COUNTRY_REGEX = '^[a-zA-Z][\w \-\.]{0,63}$';
CONST STATE_REGEX = '^[a-zA-Z][\w \-\.]{0,63}$';
CONST CITY_REGEX = '^[a-zA-Z][\w \-\.]{0,63}$';
CONST STREET_REGEX = '^[a-zA-Z][\w \-\.]{0,63}$';
CONST ZIP_CODE_REGEX = '^\d{6,12}$';
CONST COMPANY_REGEX = '^[a-zA-Z][\w \-\.]{0,255}$';
CONST COMPANY_ID_REGEX = '^[\w \-\.]{1,64}$';
































