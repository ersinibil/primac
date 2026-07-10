-- Migration: 043_stock_movements_line_pricing
-- Satış Düzenleme özelliği — satır bazlı fiyat/KDV altyapısı.
-- stock_movements bugüne kadar sadece miktar (quantity) tutuyordu; birim fiyat ve KDV oranı
-- SADECE satışın toplamında (finance_movements.amount/vat_amount) saklanıyordu, satır bazında
-- değil. Bu yüzden çoklu ürünlü bir satışı düzenlerken toplamı satırlara güvenle geri bölmenin
-- bir yolu yoktu. Bu migration, bundan SONRAKİ satışların satır bazlı fiyat bilgisini
-- saklamasını sağlar (sales.php + mobile/sales.php INSERT'leri bu kolonları dolduracak şekilde
-- ayrıca güncellendi).
--
-- Bilinçli olarak YAPILMAYANLAR:
-- - Geriye dönük veri (backfill) doldurulmadı — migration öncesi satırlarda bu kolonlar NULL
--   kalır. Tek ürünlü eski satışlarda uygulama katmanı (stock_can_edit_sale()) toplamdan güvenle
--   birim fiyatı türetip düzenlemeye izin verir; çoklu ürünlü + fiyatsız eski satışlarda ise
--   tahmin/eşit bölme YAPILMAZ, kullanıcıya "satır bazlı fiyat bilgisi yok" uyarısı gösterilir.
-- - Gerçek FOREIGN KEY constraint eklenmedi (proje genelindeki mevcut yaklaşımla tutarlı).
-- - Satır toplamı (line_total) ayrıca bir kolonda saklanmadı — quantity × unit_price + KDV
--   üzerinden ihtiyaç anında türetilir.

ALTER TABLE stock_movements
  ADD COLUMN unit_price DECIMAL(14,2) NULL
  COMMENT 'KDV hariç net birim fiyat (bu satır için, satış düzenleme özelliği için gerekli)'
  AFTER quantity,
  ADD COLUMN vat_rate DECIMAL(5,2) NULL
  COMMENT 'Bu satıra uygulanan KDV oranı (%)'
  AFTER unit_price;
