-- Migration: 018_contacts_extra_fields
-- contacts tablosuna eksik kolonları ekle (phone2, website, postal_code, iban)

ALTER TABLE contacts ADD COLUMN phone2 varchar(60) DEFAULT NULL AFTER phone;
ALTER TABLE contacts ADD COLUMN website varchar(255) DEFAULT NULL AFTER email;
ALTER TABLE contacts ADD COLUMN postal_code varchar(20) DEFAULT NULL AFTER district;
ALTER TABLE contacts ADD COLUMN iban varchar(60) DEFAULT NULL AFTER postal_code;
