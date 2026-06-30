-- Teklife giriş açıklaması (SAYIN altında görünen serbest metin)
ALTER TABLE quotes ADD COLUMN intro_note TEXT NULL AFTER customer_name;
