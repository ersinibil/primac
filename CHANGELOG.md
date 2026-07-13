# CHANGELOG.md — Özet Değişiklik Geçmişi

Bu dosya `memory/features.md`'nin (tam gerekçe/kod detayıyla) kök dizindeki kısa özetidir — hızlı
taramak için. Detaylı "neden böyle yapıldı" analizleri için `memory/features.md`'ye bakın.

## Finance CRUD UX Patch 001: CLOSED (2026-07-12, commit `1cb9e31`, USER TEST: PASS 2026-07-14)
Bir finans hareketini (tahsilat/ödeme) düzenlemek/silmek artık "Raporlar > Son İşlemler" yolunu
bilmeyi gerektirmiyor — cari detay ve hesap (kasa/banka) detay ekranları da manuel hareketlerde
doğrudan ✏️ Düzenle/🗑 Sil sunuyor, sistem kaynaklı hareketlerde ise doğru kaynağa ("🧾 Satışı Aç"/
"🛒 Alışı Aç"/"🧾 Belgeyi Aç") yönlendiriyor. Yeni bir CRUD motoru yazılmadı — mevcut
`finance_new.php`/`sil.php` yeniden kullanıldı, tek merkezi karar noktası
`finance_lib.php::finance_movement_actions()`. İşlem sonrası kullanıcı geldiği ekrana geri
dönüyor (`finance_return_url()`, open-redirect'e kapalı, Selin'in denetiminde doğrulandı). Mobil
parite (`mobile/contact_view.php`/`mobile/account_view.php` → `mobile/movement_view.php`) aynı
turda sağlandı. Detay → `memory/features.md`, `memory/bugs.md`.

## WEB UI ALIGNMENT & NAVIGATION SPRINT 001: CLOSED (2026-07-13, commit `59e51dc`..`db16565`, USER TEST: PASS)
Komuta Merkezi'ne kullanıcı bazlı sürükle-bırak eklendi — iki bağımsız seviye: Ana Modül
Kartları'nın iç sırası (`dashboard_tile_order`) ve sayfanın 10 ana bölümünün sırası
(`dashboard_section_order`, aynı `user_preferences` tablosu, migration YOK). Sol menü sadeleşti:
"Muhasebe İşlemleri" → TİCARET/FİNANS, rapor linkleri tamamen Raporlama grubuna taşındı. Ortak
tasarım dili (`.page-header`/`.row-actions`/`.quick-action`) 8 öncelikli ekrana uygulandı. Ayrı
bir turda "İş Takip"/"İş Merkezi"/"İşlerim" isim karışıklığı da "İş Emirleri"/"Görevlerim" olarak
netleştirildi (route/yetki değişmedi). Ece'nin code review'unda bulunan nested-drag "ölü bölge"
bugu (bir SECTION, kart alanına bırakılınca event'in kaybolması) aynı turda düzeltildi. Kullanıcı
DEV'de tile-drag + section-drag + sol menü + tasarım tutarlılığını PASS ile doğruladı. Detay →
`memory/features.md`, `memory/bugs.md`.

## Kontrollü Negatif Stok Politikası: CLOSED (WEB) (2026-07-11, commit `3d927c7`, USER TEST: Web PASS / Mobile Pending)
**Sistem standardı: negatif stok reddedilmez, bilinçli kullanıcı onayı istenir — onay backend'de
doğrulanır, istemci tarafı uyarısına güvenilmez.** Satış oluşturma/düzenlemede stok yetersizse
`StockShortageException` fırlatılır; `allow_negative_stock=1` onayı olmadan işlem tamamlanmaz,
DB'de hiçbir şey değişmez. Onaylanırsa satış tamamlanır, stok negatife düşebilir, açıklamaya
görünür bir işaret eklenir. Migration YOK. Selin'in incelemesinde onay checkbox'ının `<form>`
dışında kaldığı (hiç POST edilmediği) kritik bir hata bulundu ve düzeltildi. Yerel sandbox'ta 8
hedefli senaryo + tüm regresyonlar PASS, DEV'de kullanıcı PASS aldı. Mobil doğrulama Mobile
Regression Sprint'e eklendi. Detay → `memory/features.md`, `memory/bugs.md`.

## Finance Core Stabilization — Satış/Alış Artık Ödeme Yapmıyor: CLOSED (WEB) (2026-07-11, commit `d02665b`, USER TEST: Web PASS / Mobile Pending)
Kök kural: satış tahsilat yapmaz, alış ödeme yapmaz — ikisi de sadece cariye/tedarikçiye açık
borç oluşturur ("Bekliyor", kasa/banka/kart hiç etkilenmez), kapanma sadece Tahsilat/Ödeme
ekranından. Kök neden düzeltmesi: `contacts_lib.php::contact_balance_case_sql()` — session
başından beri süregelen "cari bakiye double-counting" bug'ını çözdü (satış+kendi tahsilatı aynı
yönde toplanıyordu). `purchase.php`'ye ilk kez düzenleme/silme eklendi (avg_cost güvenlik
kapısıyla). Migration YOK. Ece/Selin/Elif review'dan geçti, yerel sandbox'ta 13 zorunlu senaryo
PASS. Mobil doğrulama ayrı Mobile Regression Sprint'te. Detay → `memory/features.md`,
`memory/bugs.md`.

## Görev Detayı — "Çek / Senet Bilgileri" Kartı: CLOSED (2026-07-07, commit `b3e0def`, USER TEST: PASS — DEV/primac.tr)
Takvimdeki Çek/Senet Vadesi görevinin görev kartına, SADECE `finance` yetkisi olan kullanıcıya
görünen salt-okunur bir özet kart eklendi (tür/no/cari/banka/tutar/vade/portföy durumu/açıklama/
Finans Kaydına Git). İlişki tek kaynaklı: `checks_notes.task_id → tasks.id` (yeni
`checks_notes_get_by_task()`, parsing/tahmin yok). Güvenlik: `task_view.php`/`mobile/task_view.php`
bilinçli olarak korumasız (job_view.php deseni) olduğu için kart `user_can('finance')` ile
gate'lendi — 2026-07-02 tarihli önceki bir güvenlik denetiminin kapattığı sızıntı yeniden
açılmadı. Web'de tekil bir çek/senet görüntüleme sayfası olmadığı için yeni sayfa AÇILMADI, bunun
yerine `checks_notes.php`'ye küçük bir `?open=<id>` (scroll+highlight+auto-expand) desteği
eklendi. Migration YOK. Görev/takvim/route resolver sistemine dokunulmadı. Ece (kod inceleme) ve
Selin (güvenlik) ile teknik doğrulama PASS. Detay → `memory/features.md`.

## REOPEN-003 — WhatsApp Conversation Deneyimi: CLOSED (2026-07-07, commit `9726e14`, USER TEST: FUNCTIONAL PASS)
**Kapsam kararı**: kullanıcı bunu "basit bir bugfix değil, PRODUCT REOPEN" olarak tanımladı — amaç
sadece çalışan bir mesaj sistemi değil, gerçek bir WhatsApp Conversation deneyimi. Önce tam bir
mimari analiz yapıldı (mevcut şema, gerçek kullanıcı akışı, ürün eksikleri, önerilen mimari/ekran
yapısı, metin wireframe), sonra 7 maddelik bir mimari onay alındı, ancak uygulamaya geçmeden önce
kullanıcı bir revizyon turu daha istedi (iki panel netleştirmesi, `wa_send_now.php`'nin akıbeti,
dedup'un bu turda dahil edilmesi) — bu ikinci onay üzerine uygulandı.

**Değişenler**:
- `wa_conversation_view.php` (web) + `mobile/wa_conversation_view.php`: artık tek ekranda hem
  okuma hem yazma. `?id=` (mevcut konuşma) yanında `?phone=` de kabul ediyor — henüz konuşma
  yoksa boş thread+compose gösteriliyor, satır ilk mesaj gönderilene kadar DB'ye yazılmıyor.
  Gönderim AJAX (`ajax_send=1` POST, JSON döner) — sayfa yenilenmeden yeni balon eklenir, scroll
  en alta iner, kutu temizlenir. 3 saniyelik polling (`?poll=1&after=`) yeni mesajları otomatik
  ekliyor ve görüntülenen konuşmadaki yeni gelen mesajları anında okundu işaretliyor. Başlıkta
  artık sadece telefon değil, Firma/Cari adı + Yetkili (`contacts.authorized_person`) + Cari tipi
  gösteriliyor. 😀/📎 alanlarının yeri hazırlandı ama bilinçli olarak `disabled` — henüz aktif değil.
- Web tarafında **iki-kolon shell**: `wa_conversations.php` ve `wa_conversation_view.php` aynı
  CSS shell'i paylaşıyor (sol: arama+konuşma listesi sunucu tarafında render edilir, aktif
  konuşma vurgulanır; sağ: aktif konuşma). Gerçek SPA/client-side routing YOK — konuşma seçimi
  hâlâ normal GET navigasyonu, sadece görsel olarak iki panel hissi veriliyor (OTS'nin mevcut
  sayfa mimarisine sadık kalınarak). Liste render mantığı kopyalanmadı — yeni paylaşılan
  `wa_conversation_list_html()` (`share_lib.php`) her iki dosyada da kullanılıyor (activity_lib.php
  emsaliyle aynı desen). Mobilde tek kolon korunuyor (mobil zaten tek-sütun akışta).
- `wa_conversations.php`/mobil: arama kutusu eklendi (`?q=`, isim/telefon üzerinde LIKE).
- `wa_send_now.php`/mobil: **tekil (1:1) mod tamamen kaldırıldı** — sayfa artık SADECE "Toplu
  WhatsApp Gönderimi". 1:1 mesajlaşma tamamen `wa_conversation_view.php` üzerinden yürüyor.
  Yeni gönderim kaynağı `'wa_conversation'` `wa_log_enabled_sources()` allowlist'ine eklendi
  (bulk gönderim hâlâ `'wa_send_now'` kaynağını kullanıyor — ayrım korunuyor).
- `wa_webhook.php`/`share_lib.php`: **`provider_message_id` dedup** — `wa_message_log()` artık
  INSERT'ten önce aynı `provider_message_id`'nin zaten kayıtlı olup olmadığını kontrol ediyor.
  Webhook sağlayıcılarının (UltraMsg dahil) aynı olayı ağ/timeout nedeniyle birden fazla POST
  etmesi artık mükerrer satır açmıyor.
- `contact_view.php`/mobil: "💬 WhatsApp" butonu artık konuşma yoksa da `wa_conversation_view.php
  ?phone=X`'e gidiyor (`wa_send_now.php`'ye değil).
- `boot.php`: `$__csrf_enforced_pages`'e `wa_conversation_view.php` eklendi (artık POST/AJAX
  alıyor) — tek satır, hem web hem mobil karşılığını basename eşleşmesiyle otomatik kapsıyor.

**Bilinçli olarak bu turun DIŞINDA bırakılanlar** (WhatsApp 2.0 backlog, bkz. `ROADMAP.md`):
`fromMe` mesajların OTS dışından (telefon uygulamasından) gönderilirse görünmemesi, aynı telefonun
birden fazla cariyle eşleşmesi stratejisi, erişim izninin `users`e (admin) bağlı olması,
ack/teslim-oldu senkronizasyonu, gelen medya dosyalarının indirilmemesi.

**Teknik test**: lokal `ots_sectest`'te gerçek AJAX gönderim + simüle webhook inbound + dedup +
polling + arama + CSRF enforcement + regresyon (dashboard.php/sales.php) test edildi, 13/13 PASS
(bkz. `NEXT_SESSION.md`).

**USER TEST turu 1 — PARTIAL/BLOCKER FAIL (2026-07-07)**: production'da ekran ilk açılışta
compose/gönder butonu görünmedi (deploy henüz tamamlanmamıştı — sonra düzeldi), giden mesaj
"gönderildi" görünüp karşıya ulaşmadı, gelen mesaj hiç düşmedi. Kullanıcı "kanıtsız düzeltme
yapma" talimatıyla **geçici bir DEBUG_WA teşhis paketi** istedi: `wa_webhook.php`'nin her karar
noktasını (WEBHOOK START/JSON OK/FROM OK/FROMME TRUE-FALSE/MESSAGE ACCEPTED-REJECTED+sebep/
CONVERSATION FOUND-CREATED/MESSAGE INSERTED/RETURN 200), `wa_send()`/`wa_send_media()`'nin
provider isteği+cevabını, ve conversation ekranında admin-only canlı bir debug kutusunu
(Conversation ID/Phone/Message Count/Polling/Last Poll/Last Ajax/CSRF) `wa_debug.log`'a yazan,
UltraMsg token'ı/webhook key'i otomatik maskeleyen, `DEBUG_WA` sabitiyle kontrol edilen tamamen
geçici bir katman (commit `7afc175`).

**Kök neden KANITLANDI (production `wa_debug.log` ile)**: giden mesajlar zaten doğru çalışıyordu
(UltraMsg `"sent":"true"` dönüyordu). Gelen mesajların hiç düşmemesinin nedeni **kod DEĞİLDİ** —
UltraMsg panelinde webhook URL'i güncel/doğru tanımlı değildi (log'da tek bir `WEBHOOK START`
satırı bile yoktu, yani UltraMsg sunucuya hiç istek atmıyordu). Kullanıcı `wa_settings.php`'deki
hazır webhook URL'ini UltraMsg paneline girince **anında** çalışmaya başladı — sonraki log
`fromMe`'nin her zaman gerçek PHP boolean olarak geldiğini (ilk hipotez — string olabilir —
yanlış çıktı ama zararsızdı) ve `provider_message_id`'nin gerçekten benzersiz olduğunu (dedup
güvenli) doğruladı.

**USER TEST turu 2 — FUNCTIONAL PASS (2026-07-07)**: production'da doğrulanan ana işlevler — iki
panel ekran, konuşma listesi, konuşma detayı, compose kutusu, AJAX gönderim, polling, gelen
mesajların konuşmaya düşmesi, CSRF, Toplu Gönderim ayrımı. Debug paketi kök neden bulununca
TAMAMEN kaldırıldı (commit `7463721`) — `DEBUG_WA` sabiti, `wa_debug_log()`/`wa_debug_redact()`,
tüm çağrı noktaları, ve conversation ekranındaki debug kutusu koddan silindi (devre dışı
bırakılmadı — hiç kalmadı), `wa_debug.log` prod'da bırakılmadı.

**Durum: CLOSED.** UX açısından tam bitmiş sayılmıyor — kullanıcı bilinçli olarak bir sonraki
cilalama turunu **WHATSAPP UX 2.0** backlog'una erteledi (bkz. `ROADMAP.md`).

**Durum: USER TEST** — kullanıcı primac.tr'de gerçek görsel/tıklama testiyle onaylamadan CLOSED
sayılmayacak (REOPEN state machine).

## REOPEN-002 — Recent Activity Route Resolver: CLOSED (2026-07-06, commit `2924071`, USER TEST: PASS)
**Kök neden**: `activity_lib.php`'deki `activity_target_url()`'nin eşleme haritası `sale`/
`purchase` entity type'larını kapsamıyordu → `null` dönüyordu → çağıran (`activity_row_html()`)
KOŞULSUZ write-time'da donmuş stored `url` kolonuna düşüyordu. sale/purchase için bu stored url
her zaman boş "yeni kayıt" formuydu (`sales.php`/`purchase.php`), gerçek işlem detayına değil —
kullanıcının ekran görüntüsünde gösterdiği "Arb Reklam · kapı isimliği" tıklanınca boş satın alma
formuna gitme sorunu buydu. Analiz sırasında aynı kontrolsüz-fallback kökünden iki ayrı bulgu
daha çıktı: (1) bazı `finance` kayıtları eski/yerel geliştirme host'una donmuş stored url
taşıyordu (`http://localhost:8099/finance.php`), (2) `mobile/activity.php` paylaşılan
`activity_row_html()`/`activity_render_list()` fonksiyonlarını hiç kullanmıyordu — kendi bağımsız
kopya çözümleme mantığını taşıyordu (web'de düzeltilse mobilde düzelmezdi).

**Mimari karar**: kullanıcı dar kapsamı (sadece sale/purchase) reddetti, "bu kontrolsüz
stored-url fallback'in kendisi sorun" diyerek kontrollü geniş kapsam onayladı.

**Çözüm**:
- `activity_target_url()` → `activity_resolve($r,$platform)` olarak yeniden yazıldı, artık tek
  bir URL yerine durum döndürüyor: `ok` (güvenli hedef var), `missing` (kayıt silinmiş), `no_detail`
  (sale/purchase — per-kayıt detay ekranı hiç yok, stored url asla kullanılmaz), `unsafe_stored_url`
  (haritasız tür + stored url güvenlik kontrolünden geçemedi).
- Yeni `activity_safe_stored_url($url,$platform)`: stored url fallback'i SADECE şu durumlarda
  izin veriyor — host güncel `base_url()` domain'iyle eşleşiyor, URL'de port yok, web ekranında
  `mobile/` klasörüne gitmiyor, ve bare (önek'siz) dosya adı karşı platformun klasöründe
  gerçekten var (`is_file()` ile kontrol). Bu tek fonksiyon hem sale/purchase-dışı `finance`/
  `category`/`brand`/`unit`/`system`/`admin` gibi haritasız türleri, hem eski-host hem de
  çapraz-platform senaryolarını kapsıyor — özel tür bazlı kod eklemeden.
  Deployment note: 'finance' tipi için gerçekten mobile-only bir hedef (mobile/finance.php)
  yok; bu satırlar artık mobilde pasif gösteriliyor (kırık link yerine), web'de bare
  'finance.php' hâlâ çalışıyor.
- `activity_row_html()` 4 durumu da işliyor — `no_detail`/`unsafe_stored_url` için "Bu işlem için
  detay ekranı henüz yok" / "Kayıt detayına güvenli şekilde ulaşılamıyor" pasif (tıklanamaz) satır.
- `mobile/activity.php` artık kendi kopya mantığı yerine `activity_render_list($rows,'mobile')`
  çağırıyor (Seçenek A — mimari onaylandı); `mobile/common.php`'ye eksik `.activity-item`/
  `.activity-icon`/`.activity-body`/`.activity-list` CSS sınıfları mevcut dark-theme
  token'larıyla (`var(--c-muted)`, `var(--radius-*)`, `rgba(255,255,255,.1)`) eklendi.
- Sadece 3 dosya değişti: `activity_lib.php`, `mobile/activity.php`, `mobile/common.php`. Yeni
  tablo/DB değişikliği yok, UI redesign yok, mapped türlerin (contact/job/task/quote/product/
  stock/personnel/trade_document) davranışı BİREBİR aynı kaldı (regresyon testiyle doğrulandı).

**Teknik test**: lokal `ots_sectest`'te gerçek satın alma/satış submit edilip `activity_logs`
satırı → `activity.php`/`dashboard.php`/`mobile/activity.php` render çıktısı birebir doğrulandı,
11 maddelik test planının tamamı PASS (bkz. `NEXT_SESSION.md`).

**Deploy notu (önemli ders)**: ilk kullanıcı testi PARTIAL FAIL sonucu verdi — "Arb Reklam" veresiye
satın alması hâlâ boş bir forma gidiyordu. Kök neden kodda DEĞİLDİ: `git push` yapılmıştı ama
primac.tr'nin gerçek deploy mekanizması (`~/Desktop/PRIMAC-GUNCELLEME/guncelleme.zip` + sunucuda
`guncelle.php` çalıştırma) bu turda atlanmıştı — zip 2026-07-05 21:41 tarihliydi, REOPEN-001'i bile
içermiyordu. Zip `git archive HEAD`+`vendor/` ile tazelenip (MD5 ile repo'yla eşleştiği doğrulandı),
kullanıcı cPanel'den yükleyip `guncelle.php`'yi çalıştırdıktan SONRA gerçek test PASS verdi: "Arb
Reklam" kaydı artık pasif, tıklanamaz, "Bu işlem için detay ekranı henüz yok" notuyla gösteriliyor.
**Ders**: "Push" adımı bundan sonra SADECE `git push` değil, DEV için Masaüstü zip'inin de
tazelenip kullanıcıya "yükle+guncelle.php çalıştır" hatırlatmasının yapılması demek — bkz.
`memory/deploy.md`.

Kullanıcı ayrıca bu test sırasında satın alma/satış için GERÇEK bir detay ekranı istediğini belirtti
— bu, REOPEN-002'nin (routing güvenliği) kapsamı dışında, bilinçli olarak **Purchase & Sales 2.0**'a
ertelendi (bkz. `ROADMAP.md` "Bilinçli olarak ERTELENMİŞ").

**Durum: CLOSED** — kullanıcı primac.tr'de gerçek deploy sonrası tıklama testiyle onayladı.

## REOPEN-001 — Calendar Daily Filter: CLOSED (2026-07-06, commit `0ecdf80`, kullanıcı testinde PASS)
**Kök neden** (3 turluk kök neden analizi + kullanıcının kendi üretim ekran görüntüsüyle
kesinleştirildi): `takvim.php`/`mobile/calendar.php`'nin GÜN FİLTRESİ (`$byDay[$g]`) her zaman
doğru çalışıyordu — hem grid hem detay panel gerçek veriyle test edilip doğrulanmıştı. Asıl sorun:
takvimdeki **not (📝) türü** öğelerin linki, hiçbir tarih bilgisi taşımadan düz `notes.php`
(web) / `mytasks.php` (mobil) sayfasına gidiyordu — bu sayfalar `personal_notes_list()` ile
kullanıcının TÜM açık notlarını (tarihe göre artan sırada, filtresiz) gösteriyor. Kullanıcı 6
Temmuz'un bir notuna tıklayınca "6 Temmuz'un notu" değil, "tüm açık hatırlatmalarım" ekranına
düşüyordu — bu da "6, 7, 8 Temmuz sırayla geliyor, gün filtresi çalışmıyor" izlenimini birebir
açıklıyor. Takvimin kendi ızgara/panel mantığına HİÇ dokunulmadı, çünkü zaten doğruydu.

**Çözüm**: `notes.php` + `mobile/mytasks.php` + `personal_notes_list()` (`notes_lib.php`) opsiyonel
`date`/`?date=YYYY-MM-DD` parametresi destekler hale getirildi (parametre verilmezse — sol
menüden normal erişimde olduğu gibi — davranış birebir eskisiyle aynı). `takvim.php` ve
`mobile/calendar.php`'deki not linklerine kendi günlerinin tarihi eklendi. Sadece 5 dosya, 30
satır eklendi — POST/not-ekleme-düzenleme-silme akışlarına HİÇ dokunulmadı (mevcut "Notlarım genel
liste" davranışı bilinçli olarak korundu).

**Teknik test** (yerel `ots_sectest`, gerçek HTTP, 6/7/31 Temmuz'a test notları): sol menüden
Notlarım (date yok) → tüm notlar (PASS); takvimden 6 Temmuz notuna → sadece 6 Temmuz (PASS, 7 ve 31
görünmüyor); takvimden 31 Temmuz notuna → sadece 31 Temmuz (PASS); aynı senaryolar mobilde
(`mobile/calendar.php`→`mobile/mytasks.php?date=`) PASS; hatalı/SQL-injection/XSS denemeli `date`
parametreleri → sistem bozulmadı, güvenli şekilde ya tüm listeye düştü ya boş sonuç gösterdi (PASS);
not ekleme POST akışı regresyonsuz (PASS); GET regresyon taraması 7/7 200; `php -l` 5/5 temiz;
server log'da hata yok. FAIL yok.

**Durum: CLOSED** (2026-07-06) — kullanıcı primac.tr'de gerçek sistemde test etti, **USER TEST:
PASS** onayı verdi. REOPEN durum makinesi tamamlandı: OPEN → IN PROGRESS → USER TEST → **PASS**
(bkz. `memory/feedback_evolution_not_revolution.md`). Sıradaki: **REOPEN-002 — Recent Activity
Route Resolver**.

## SECURITY SPRINT-005 — FINAL AUDIT: PASS — Sprint Resmen Kapandı (2026-07-05)
Kapsam: Login Hardening — `index.php` login formu + `boot.php`'deki oturum/remember-me altyapısı.
4 faz, **sıfır FAIL**, sadece `index.php`/`boot.php`/`share_lib.php`/`.gitignore` değişti (başka
hiçbir modüle dokunulmadı):

- **FAZ-1 — Session Fixation Hardening: PASS** (`b973e01`) — `index.php` login sonrası +
  `boot.php` `remember_check()` sonrası `session_regenerate_id(true)`.
- **FAZ-2 — Login CSRF Hardening: PASS** (`f20e50d`) — SADECE `index.php`, form'a `csrf_field()` +
  mevcut try/catch'e gömülü token kontrolü (403 yerine dost mesajla login ekranında kalma).
- **FAZ-3 — Login Brute-Force/Rate-Limit: PASS** (`310882a`) — `index.php` + `share_lib.php`
  (`rate_limit_blocked()`/`rate_limit_hit()`/`rate_limit_clear()`, ayrı `login_ratelimit.json`),
  IP+kullanıcı adı bazında 10 dk'da 8 başarısız deneme.
- **FAZ-4 — Remember-Me Hardening: PASS** (`dc92a6e`) — SADECE `boot.php`, token rotasyonu (her
  otomatik girişte eski token geçersiz) + `acans_remember`'a `SameSite=Lax` (path-hack'in modern
  PHP'de `setcookie()` ValueError'ına çarptığı bulundu, ham `Set-Cookie` header'ı `header()` ile
  elle oluşturuldu — hem PHP 7.2 hem 8.x'te doğrulandı).
- **FAZ-5 — Timing/Enumeration: bilinçli olarak UYGULANMADI** (kullanıcı kararı, 2026-07-05) — LOW
  risk, ürün güvenliği açısından kritik değil, gereksiz regresyon riski istenmedi.

**FINAL AUDIT** (2026-07-05, kod değiştirilmeden, yerel `ots_sectest` + gerçek HTTP ile bağımsız
yeniden doğrulama): Session Fixation, Login CSRF, Brute-Force/Rate-Limit, Remember-Me Rotation,
Remember-Me SameSite, session davranışı, logout, `return_to`, genel remember-me — **10/10 PASS**,
regresyon YOK (6 GET ekranı 200/200, `php -l` 3/3 temiz, server log'da hata/uyarı yok). FAZ-2/3/4'ün
aynı dosyalar üzerindeki üst üste binen değişiklikleri birbirini bozmadı.

**Sonuç: PASS.** Login formu artık CSRF korumalı ve hız sınırlı; oturum kimliği kimlik doğrulama
sonrası her zaman yenileniyor; remember-me token'ları tek kullanımlık (rotasyonlu) ve
`SameSite=Lax` ile korunuyor. Detay → `VERSIONING.md` "Security Sprint Durumu", `ROADMAP.md`
"Security Roadmap".

## SECURITY SPRINT-005 FAZ-4 — Remember-Me Hardening: PASS (2026-07-05, commit `dc92a6e`)
Kapsam: SADECE `boot.php` (`share_lib.php`'ye dokunulmadı, gerek kalmadı — remember-me fonksiyonları
zaten `boot.php`'de). Yeni bir remember-me tablo mimarisi kurulmadı, tek-cihaz/tek-token davranışı
korundu.

**1. Token rotasyonu**: `remember_check()`'te otomatik giriş başarılı olduğunda artık `remember_set()`
tekrar çağrılıyor (aynı fonksiyon normal login'de de kullanılıyor, kod tekrarı yok) — yeni token
üretilip DB'deki hash güncelleniyor, cookie yeni token ile yenileniyor. Kullanılan token o istekten
sonra GEÇERSİZ.

**2. SameSite**: `acans_remember` cookie'sine `SameSite=Lax` eklendi. **Önemli bulgu**: PHP 7.2'nin
setcookie() imzasının SameSite desteklemediği bilinen "path hack"i (`path` parametresine
`;samesite=Lax` eklemek) yerel test ortamında (PHP 8.5) `setcookie(): "path" option cannot contain
";"` **ValueError**'ına çarptı — DB güncelleniyor ama cookie hiç gönderilmiyordu, hata `try/catch`
ile sessizce yutuluyordu (fail-open ama YANLIŞ). Bu yüzden ham `Set-Cookie` header'ı `header()` ile,
native `setcookie()`'nin ürettiğiyle birebir aynı isim/değer/expiry/Max-Age/Path/HttpOnly/Secure'a ek
olarak `SameSite=Lax` ile elle oluşturuldu — hem PHP 7.2 hem 8.x'te aynı şekilde çalıştığı
doğrulandı. `remember_clear()`'ın silme çağrısı DEĞİŞTİRİLMEDİ (plain `/` path, SameSite gerekmiyor,
aynı ValueError riskini taşımasın diye).

Yerel `ots_sectest` + `php -S` + gerçek HTTP istekleriyle (cookie jar bazlı) test edildi:
- Remember-Me ile giriş: **PASS** — cookie oluştu, `Set-Cookie` başlığında `SameSite=Lax` gerçekten
  var (curl ile doğrulandı), DB'de hash kaydedildi.
- Anonim oturum + remember cookie → otomatik giriş: **PASS** — dashboard açıldı, session id de
  (FAZ-1) hâlâ regenerate oluyor.
- Token rotasyonu: **PASS** — otomatik giriş sonrası cookie'deki token DEĞİŞTİ (orijinalden farklı).
- Eski token tekrar kullanılamıyor: **PASS** — eski cookie ile erişim `/index.php`'ye redirect oldu.
- Yeni (rotate edilmiş) token ile otomatik giriş: **PASS**.
- Logout: **PASS** — remember cookie silindi (`deleted`/`Max-Age=0`) + DB `remember_token` NULL
  oldu, eski cookie sonrasında işe yaramadı.
- Normal login, Rate-Limit (FAZ-3), Login CSRF (FAZ-2): **PASS** — hiçbiri bozulmadı.
- `php -l` temiz. Server log'da hata/uyarı/ValueError yok. FAIL yok.

**Sonuç**: Remember-me statik token riski kapandı (artık her kullanımda rotate oluyor, çalıntı bir
token tek kullanımlık). SameSite eklendi. Kalan Login Hardening riskleri: timing/enumeration
(kullanıcı yokken `password_verify()` atlanması — LOW, isteğe bağlı). Sıradaki önerilen faz:
**FAZ-5 — Timing/Enumeration** (isteğe bağlı, düşük öncelik) veya doğrudan **FAZ-6 — FINAL AUDIT**
(FAZ-5 düşük öncelikli/isteğe bağlı olduğu için atlanabilir). Kullanıcı onayı bekliyor, otomatik
geçilmedi.

## SECURITY SPRINT-005 FAZ-3 — Login Brute-Force/Rate-Limit: PASS (2026-07-05, commit `310882a`)
Kapsam: `index.php` + `share_lib.php` (+ `.gitignore`'a 1 satır `login_ratelimit.json`). Yeni bir
rate-limit sistemi YAZILMADI — `sifre_sifirla.php`'nin kanıtlanmış dosya-tabanlı/`flock`'lu/
fail-open deseni `share_lib.php`'ye `rate_limit_blocked()`/`rate_limit_hit()`/`rate_limit_clear()`
olarak genelleştirildi (check/hit/clear'a ayrıldı çünkü login SADECE başarısız denemeleri saymalı,
başarılıda temizlemeli — `sifre_sifirla.php`'nin "her istek sayılır" modelinden farklı). Ayrı
`login_ratelimit.json` dosyası kullanılıyor — `reset_ratelimit.json` ile ASLA karışmaz.
Bucket anahtarı **IP+kullanıcı adı** (`$ip.'|'.strtolower($username)`) — eşik 10 dakikada 8
başarısız deneme.

Yerel `ots_sectest` + `php -S` + gerçek HTTP istekleriyle test edildi:
- Tek doğru giriş: **PASS**.
- Art arda 8 yanlış şifre denemesi: **PASS** — hepsi normal "Kullanıcı adı veya şifre hatalı" mesajı,
  henüz engellenmedi.
- 9. deneme (limit dolu): **PASS** — "Çok fazla başarısız giriş denemesi..." mesajı.
- Limit süresince DOĞRU şifre bile: **PASS** — engellendi (kimlik doğrulamaya hiç geçilmedi,
  hit sayısı artmadı).
- Pencere süresi dolunca (timestamp'ler simüle edilerek): **PASS** — doğru şifreyle giriş başarılı.
- Başarılı giriş sonrası sayaç temizlendi: **PASS** — dosyada bucket key silindi.
- Remember-Me, `return_to`, Logout: **PASS** — hiçbiri bozulmadı (FAZ-1/FAZ-2 ile birlikte
  regresyonsuz çalışıyor).
- Bonus: aynı IP'den FARKLI kullanıcı adı engellenmedi (per-account anahtar doğrulandı, blanket
  IP kilidi yok).
- `php -l` 2/2 temiz. Server log'da hata yok. FAIL yok.

**Sonuç**: Login brute-force koruması tamamlandı. Kalan riskler: remember-me token rotasyonu yok +
`acans_remember` cookie'sinde SameSite bayrağı yok (MEDIUM, FAZ-4 kapsamı). Sıradaki önerilen faz:
**FAZ-4 — Remember-Me Hardening**. Kullanıcı onayı bekliyor, otomatik geçilmedi.

## SECURITY SPRINT-005 FAZ-2 — Login CSRF Hardening: PASS (2026-07-05, commit `f20e50d`)
Kapsam: SADECE `index.php` login formu CSRF'i. `boot.php`'ye dokunulmadı (mevcut `csrf_token()`/
`csrf_field()`/`hash_equals` yeniden kullanıldı, yeni fonksiyon eklenmedi). Login formuna
`csrf_field()` eklendi; POST işleyicisinin en başına, mevcut try/catch akışına gömülü bir token
kontrolü eklendi (10 satır, tek dosya) — başarısızsa `csrf_verify()`'nin enforced-liste yolundaki
gibi 403 sayfasına DÜŞMÜYOR, aynı `catch(Throwable $e){ $error=$e->getMessage(); }` mekanizmasıyla
kullanıcı normal login ekranında "Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar
deneyin." mesajıyla kalıyor — en yüksek blast-radius dosya olduğu için bilinçli olarak enforced-liste
yerine bu daha güvenli/nazik yöntem seçildi.

Yerel `ots_sectest` + `php -S` + gerçek HTTP istekleriyle (cookie jar bazlı) test edildi:
- Token'lı login: **PASS** (gerçek başarı, `dashboard.php`'ye redirect).
- Token'sız login: **PASS** — HTTP 200 (403 DEĞİL), login gerçekleşmedi (dashboard'a erişim hâlâ
  `/index.php`'ye redirect), aynı ekranda dost hata mesajı gösterildi.
- Hatalı/bozuk token: **PASS** — aynı davranış (200, login yok, dost mesaj).
- Remember-Me: **PASS** — bozulmadı, FAZ-1'in session-regenerate davranışı da hâlâ çalışıyor (en
  katı senaryo: anonim oturum + remember cookie ile tekrar doğrulandı).
- `return_to`: **PASS** — bozulmadı (`/contacts.php`'ye doğru redirect).
- Logout: **PASS** — bozulmadı (remember cookie temizlendi, `/index.php`'ye redirect).
- `php -l` temiz. Server log'da hata/uyarı yok. FAIL yok.

**Sonuç**: Login CSRF riski kapandı. Kalan HIGH risk: login brute-force/rate-limit eksikliği.
Sıradaki önerilen faz: **FAZ-3 — Login Rate Limit**. Kullanıcı onayı bekliyor, otomatik geçilmedi.

## SECURITY SPRINT-005 FAZ-1 — Session Fixation Hardening: PASS (2026-07-05, commit `b973e01`)
Kapsam: SADECE session fixation riski. `index.php` başarılı login sonrası ve `boot.php`
`remember_check()`'te remember-me ile başarılı otomatik giriş sonrası `session_regenerate_id(true)`
eklendi (toplam 2 satır, 2 dosya). Login CSRF, rate-limit, remember-me rotasyonu, cookie değişikliği
bilinçli olarak bu fazın DIŞINDA bırakıldı (ayrı fazlar).

Yerel `ots_sectest` + `php -S` + gerçek HTTP istekleriyle (cookie jar bazlı) test edildi:
- Normal login: login öncesi/sonrası `PHPSESSID` **farklı** (PASS), session verisi (`$_SESSION['user']`)
  korunmuş — regenerate sonrası `dashboard.php` login'e düşmeden açıldı (PASS).
- Remember-Me: önceden var olan anonim bir `PHPSESSID`'ye `acans_remember` çerezi eklenip
  `dashboard.php`'ye erişildiğinde otomatik giriş oldu VE `PHPSESSID` **farklı** bir değere değişti
  (PASS — en katı senaryo: var olan girişsiz oturum + remember cookie birlikte test edildi).
- Logout: `acans_remember` çerezi temizlendi (`deleted`/`Max-Age=0`), sonraki istek `/index.php`'ye
  redirect oldu (PASS).
- `return_to`: girişsiz halde korumalı bir sayfaya (`contacts.php`) direkt erişim → login'e
  yönlendirme → login sonrası **doğru sayfaya** (`/contacts.php`, `dashboard.php`'ye değil) redirect
  (PASS) — regenerate, `$_SESSION['return_to']`'yu bozmadı.
- Çoklu sekme: aynı cookie jar ile 3 eşzamanlı istek (dashboard/contacts/tasks) — tümü 200, server
  log'da hata/uyarı yok (PASS).
- `php -l` 2/2 temiz. FAIL yok.

**Sonuç**: Session fixation riski (önceki SPRINT-005 PREPARATION REPORT'ta CRITICAL olarak
işaretlenmişti) kapandı. Kalan CRITICAL risk yok. Sıradaki önerilen faz: **FAZ-2 — Login CSRF**
(`index.php` companion-fix). Kullanıcı onayı bekliyor, otomatik geçilmedi.

## SECURITY SPRINT-004 — FINAL AUDIT: PASS — Sprint Resmen Kapandı (2026-07-05)
Kapsam: merkezi CSRF (Cross-Site Request Forgery) koruma altyapısının kurulması ve kademeli olarak
(yüksek riskten düşük riske) tüm CRUD/işlem POST endpoint'lerine yayılması. **57 benzersiz basename**
enforced (`boot.php` `$__csrf_enforced_pages`, basename eşleşmesi web+mobil aynı isimli dosyaları
otomatik kapsıyor), **15 modül/işlevsel grup** etkilendi, **15 commit** üretildi (tamamı push
edildi): `90dffa7, a32893c, 198e541, 4708cd6, 9b08296, ae8116a, a97ccee, a68637a, 4f329c3, 48d943f,
be032b2, b4b2c9a, dee36eb, 7077a6d, e108dad`.

**Fazlar (sırayla, tamamı PASS)**: FAZ-1 (altyapı: `csrf_token()`/`csrf_field()`/`csrf_verify()` +
auto-inject) → FAZ-2 (AJAX `X-CSRF-Token` header) → FAZ-3A (pilot: `users.php`/`sil.php`) → FAZ-3B
(Bildirimler) → FAZ-3C (Finans/Muhasebe) → FAZ-4A (Finans işlem ekranları) → FAZ-4B (Personel) →
FAZ-4C (Kimlik/Sistem) → FAZ-4D (Mali belge/Teklif) → FAZ-4E (WhatsApp gönderim) → FAZ-4F (İş/Görev
detay) → **HIGH-RISK CHECKPOINT AUDIT: PASS** → FAZ-5A (CRM, `4708cd6`) → FAZ-5B (Stok/Ürün,
`ae8116a`) → FAZ-5C (İş/Görev ana formları, `a68637a`) → FAZ-5D (Mesajlaşma/Talep, `48d943f`) →
FAZ-5E (Satış/Satın Alma, `b4b2c9a`) → FAZ-5F (Temizlik grubu, `7077a6d`).

**Konsolide test/regresyon durumu**: her fazda yerel `ots_sectest` MariaDB + gerçek `php -S` HTTP
istekleriyle token'lı POST (gerçek DB etkisiyle başarı) ve token'sız POST (403) doğrulandı. Sayısal
belgelenen fazlar: FAZ-5B 12/12, FAZ-5C 14/14, FAZ-5E 4/4, FAZ-5F 5/5 dosya — GET regresyonu FAZ-5B
10/10, FAZ-5C 16/16, FAZ-5D 14/14, FAZ-5E 10/10, FAZ-5F 14/14 (tümü 200); `php -l` FAZ-5B 13/13,
FAZ-5C 15/15, FAZ-5D 10/10, FAZ-5E 5/5, FAZ-5F 9/9 temiz. **Sprint boyunca sıfır FAIL, sıfır
regresyon.**

**Bilinçli olarak sprint dışı bırakılanlar**: `index.php` (login formu) CSRF'i — companion-fix
gerektiriyor (`layout_top.php`'den geçmiyor), en yüksek blast-radius dosya — **kullanıcı onayıyla
SECURITY SPRINT-005'e taşındı** (Login Hardening: login CSRF + session fixation + session rotation +
cookie hardening + remember-me incelemesi + login brute-force/rate-limit). `job_view.php`/
`task_view.php` yetki modeli (bilinçli olarak `page_module_map()` dışında) ayrı Authorization
Audit/SPRINT-009 kapsamına bırakıldı.

**Açık teknik borçlar** (CSRF'siz, sprint kapsamı dışında): `requests.php`/`mobile/requests.php`
`manager_note`/`response_note` schema-drift bug'ı (FAZ-5D'de bulundu), rate-limit'in JSON dosyadan
merkezi tabloya taşınması, `REMOTE_ADDR` reverse-proxy güvenilirliği, `KNOWN_BUGS.md`'deki
`accounting.php` XSS + `users.php` rol yükseltme (henüz sprint numarası atanmadı), WhatsApp MVP'nin 7
teknik borç maddesi.

**Sonuç: PASS.** Sprint'in kendi kapsamı (merkezi CSRF altyapısının tüm CRUD/işlem endpoint'lerine
yayılması) sıfır FAIL ve sıfır regresyonla tamamlandı; `index.php` bilinçli, gerekçeli bir kapsam
kararıyla SPRINT-005'e devredildi. Detay → `VERSIONING.md` "Security Sprint Durumu", `ROADMAP.md`
"Security Roadmap".

## SECURITY SPRINT-004 FAZ-5F — "Temizlik" Grubu CSRF Enforcement: PASS (2026-07-05, commit `7077a6d`)
Kapsam: `accounting_categories.php` (web+mobil), `check_note_view.php` (**mobil-only**),
`report.php` (web GET-only, mobil `send_msg` POST var), `ajax_quick_add.php` (**web-only** ama
web+mobil `sales.php`/`purchase.php`/`checks_notes.php` formlarından ortak AJAX çağrısıyla
kullanılıyor), `wa_settings.php` — `$__csrf_enforced_pages` listesine eklendi.

Yerel `ots_sectest` QA'da: muhasebe kategorisi ekleme (web+mobil), çek/senet düzenleme
(`mobile/check_note_view.php` — tutar/durum alanları bozulmadan sadece not güncellendi, finansal
enstrüman davranışı korundu), rapor CSV+iç mesaj gönderimi (mobil `send_msg`, gerçek dosya ekli
mesaj oluştu), inline hızlı cari/ürün ekleme (`ajax_quick_add.php` — gerçek `X-CSRF-Token` header'ı
ile, `sales.php`'nin JS'inin kullandığı BİREBİR format, hem `t=contact` hem `t=product` PASS),
WhatsApp ayarları kaydetme (admin-only davranışı korunuyor, kod dokunulmadı) — tamamı token'lı POST
başarılı + token'sız POST 403. GET regresyon taraması (14 ekran) 14/14 200, `php -l` 9/9 temiz,
server log'da hiç hata/uyarı yok. FAIL yok. `index.php` ve `requests.php`/`manager_note`
schema-drift bulgusuna bu fazda dokunulmadı.

## SECURITY SPRINT-004 FAZ-5E — Satış/Satın Alma Modülü CSRF Enforcement: PASS (2026-07-05, commit `b4b2c9a`)
Kapsam: `sales.php`, `purchase.php` (web+mobil) — `$__csrf_enforced_pages` listesine eklendi. Yerel
`ots_sectest` QA'da her iki dosya için gerçek satış/satın alma kaydı oluşturuldu ve şu etkiler
doğrulandı: satışta `stock_items.quantity` düşüyor + `finance_movements` 'in'/'sale' hareketi
oluşuyor (KDV dahil tutar doğru); satın almada `stock_items.quantity` artıyor +
`finance_movements` 'out'/'purchase' hareketi oluşuyor. Token'lı POST (4/4 dosya-örneği: web+mobil
× sales/purchase) PASS, token'sız POST 403 PASS. GET regresyon taraması (10 ekran) 10/10 200,
`php -l` 5/5 temiz, server log'da hiç hata/uyarı yok. FAIL yok. `requests.php`/`manager_note`
schema-drift bulgusuna (FAZ-5D'de tespit edildi) bu fazda dokunulmadı.

## SECURITY SPRINT-004 FAZ-5D — Mesajlaşma/Talep Modülü CSRF Enforcement: PASS (2026-07-05, commit `48d943f`)
Kapsam: `messages.php`, `notes.php`, `request_new.php`, `requests.php`, `profile.php` —
`$__csrf_enforced_pages` listesine eklendi (`notes.php`'nin mobil karşılığı `mytasks.php`'ye
gömülü, FAZ-5C'de zaten enforced idi, ek işlem gerekmedi). Yerel `ots_sectest` QA'da iç mesajlaşma
(gönder/düzenle/sil — hepsi gerçek DB kaydı ile doğrulandı), kişisel not ekleme/güncelleme, talep
oluşturma/durum güncelleme, profil+şifre güncelleme — web+mobil token'lı POST (gerçek başarı) ve
token'sız POST (403) **PASS**. GET regresyon taraması (14 ekran) 14/14 200, `php -l` 10/10 temiz.
FAIL yok.

**Yan bulgu (CSRF ile ilgisiz, dokunulmadı)**: `requests.php`/`mobile/requests.php`'de önceden var
olan bir schema-drift hatası bulundu — kod `manager_note` kolonuna yazıyor ama gerçek tablo kolonu
`response_note`; talep durumu/not güncellemesi sessizce başarısız oluyor, hata hiç ekrana
basılmıyor (`$error` set ediliyor ama render edilmiyor). Bu fazın kapsamı dışında bırakıldı, ayrı
bir bug-fix turu gerektiriyor — bkz. `ROADMAP.md`.

## SECURITY SPRINT-004 FAZ-5C — İş/Görev Modülü CSRF Enforcement: PASS (2026-07-05, commit `a68637a`)
Kapsam: `job_new.php`, `jobs.php`, `task_new.php`, `tasks.php`, `mytask_new.php`, `mytasks.php`
(web+mobil) + `uretim_new.php`, `group_new.php` (**mobil-only**, web karşılığı hiç yok) —
`$__csrf_enforced_pages` listesine eklendi. Yerel `ots_sectest` QA'da 8 dosyanın tamamı (14
dosya-örneği: 6×web+mobil + 2×mobil-only) için token'lı POST (gerçek iş/görev/üretim emri/grup
sohbeti oluşturma + durum güncelleme başarılı, admin hesabının personel bağlantısı olmadığı için
`mytask_new.php`/`mytasks.php` testleri personel-bağlı test kullanıcısı `omer` ile tekrarlandı) ve
token'sız POST (403) — **14/14 PASS**. GET regresyon taraması (16 ekran) 16/16 200, `php -l` 15/15
temiz. FAIL yok. Test verisi temizlendi, `config.php` diff ile doğrulanarak orijinaline geri
yüklendi (yerel `ots_sectest`'teki `omer` test kullanıcısının şifresi, orijinal hash'i bu oturumda
hiç kayıt altına alınmadığı için `altyonetici` ile aynı standart QA test şifresine ayarlı kaldı —
sadece yerel sandbox DB, production'ı etkilemiyor).

## SECURITY SPRINT-004 FAZ-5B — Stok/Ürün Modülü CSRF Enforcement: PASS (2026-07-05, commit `ae8116a`)
Kapsam: `product_new.php`, `product_view.php`, `product_categories.php`, `product_taxonomy.php`,
`stock_movement_new.php`, `brand_settings.php` — `$__csrf_enforced_pages` listesine eklendi
(basename eşleşmesi aynı-isimli `mobile/` karşılıklarını otomatik kapsıyor, ek kod gerekmedi).
Yerel `ots_sectest` QA'da 6 dosyanın tamamı için web+mobil token'lı POST (gerçek kayıt/güncelleme
başarılı) ve token'sız POST (403) — **12/12 PASS**. GET regresyon taraması (10 ekran) 10/10 200,
`php -l` 13/13 temiz. FAIL yok. Test verisi temizlendi, `config.php` diff ile doğrulanarak
orijinaline geri yüklendi.

## WhatsApp Conversation/Inbound MVP — PASS (2026-07-05, commit `dae3e62`)
Kullanıcı bildirimi: OTS'den WhatsApp mesajı gönderiliyor ama karşı tarafın cevabı sistemde hiç
görünmüyor, kalıcı konuşma geçmişi yok. **Analiz**: provider **UltraMsg**; `wa_send()`/
`wa_send_media()` (`share_lib.php`) sadece gönderiyor, HİÇBİR şeyi DB'ye yazmıyordu; hiçbir inbound
webhook, hiçbir konuşma/mesaj tablosu yoktu.

**Migration `041_whatsapp_conversations.sql`**: `wa_conversations` (phone normalize+unique,
contact_id nullable, last_message_at/preview/direction, unread_count) + `wa_messages`
(conversation_id, direction, **source**, body, media_url/type, provider_message_id, status,
is_read). `share_lib.php::wa_install()` aynı şemayı self-heal olarak da taşıyor
(`activity_install()` ile aynı desen).

**Sender-scope genişleyebilir mimari**: `wa_log_enabled_sources()` allowlist'i + `wa_send_logged()`
sarmalayıcısı — gönderme davranışı `wa_send()`/`wa_send_media()` ile birebir aynı, SADECE
`$source` allowlist'teyse ve gönderim başarılıysa conversation history'ye yazılıyor. **Bugün
sadece `wa_send_now.php` (web+mobil) etkin.** `sifre_sifirla.php` (OTP), `users.php`/
`mobile/users.php` (giriş bilgisi), `daily_reminder_lib.php` (otomatik rapor), `notes_lib.php`,
`mobile/wa_settings.php` **HİÇ DEĞİŞTİRİLMEDİ** — ham `wa_send()` çağırmaya devam ediyorlar,
hassas/tek kullanımlık içerik kalıcı geçmişte durmuyor. Yarın başka bir modülü dahil etmek tek
satır (allowlist'e ekle + o çağrıyı `wa_send_logged()`'e çevir).

**`wa_webhook.php`** (yeni, public, `boot.php` `$__mpub`'a eklendi): UltraMsg'in gerçek payload
şemasına (`data.from/body/type/id/fromMe`) göre inbound mesajı işliyor; sabit kod-içi anahtar
yerine `wa_settings.php`'de otomatik üretilip DB'de saklanan rastgele `?key=` ile korunuyor
(`KNOWN_BUGS.md`'deki "sabit anahtar" anti-pattern'i tekrarlanmadı). `fromMe=true` (kendi
yankımız) tekrar loglanmıyor (zaten gönderim anında yazıldı). Telefon→cari eşleştirmesi
`_wa_normalize_phone()` ile mevcut `contacts.phone`/`phone2`'ye karşı yapılıyor, eşleşme yoksa
sadece telefonla conversation açılıyor.

**Ekranlar**: `wa_conversations.php` + `wa_conversation_view.php` (web), `mobile/wa_conversations.php`
+ `mobile/wa_conversation_view.php` (mobil — okunabilir + mevcut `wa_send_now.php`'ye yönlendirme,
ayrı bir compose kutusu icat edilmedi). `contact_view.php`/`mobile/contact_view.php`'ye "💬
WhatsApp" linki eklendi (konuşma varsa direkt oraya, yoksa telefon ön-dolu gönderme ekranına).
Menü: `layout_top.php` "Mesajlar" grubu, `mobile/more.php`.

**Test** (yerel `ots_sectest`, stub "UltraMsg" sunucusuyla gerçek başarılı gönderim simüle edildi):
outbound log + contact eşleşmesi (PASS), inbound webhook geçerli mesaj/`fromMe=true` dedup/yanlış
key 403/bozuk JSON crash yok (4/4 PASS), eşleşmeyen numaradan gelen mesaj → `contact_id=NULL` yeni
conversation (PASS), web+mobil liste/detay ekranları (okunma sıfırlama, mine/theirs bubble, PASS),
`contact_view.php` entegrasyonu (PASS), migration dosyası doğrudan çalıştırıldı (idempotent,
PASS), OTP/sistem mesajı dosyaları değişmedi (`git diff` ile doğrulandı), regresyon yok (`php -l`
14/14 temiz). FAIL yok.

## UX/STABILITY PATCH-004 — Son İşlemler Route Resolver: PASS (2026-07-05, commit `dff59d5`)
Kullanıcı bildirimi: "Son İşlemler" listesindeki kayıtlar yanlış sayfaya gidiyor, bazıları mobil
route açıyor, bazıları çalışmıyor. **Kök neden**: `activity_logs.url` her `activity_log()` çağrısında
YAZMA anında sabit bir string olarak donduruluyordu, render anında `entity_type`/`entity_id` hiç
kullanılmıyordu — merkezi bir resolver yoktu. ~65 çağrı noktasından bazıları (ör.
`mobile/personnel_new.php`) tek platforma kilitli mutlak url kaydediyordu; web ve mobil Son
İşlemler ekranları bu stored url'i sorgusuz basıyordu (web'de mobil route, mobilde web route
açılabiliyordu). Ayrıca silinmiş kayıtlara giden linkler hiç kontrol edilmiyordu.

**Çözüm**: `activity_lib.php`'ye `activity_target_url($entityType,$entityId,$platform)` eklendi —
render anında DB'den varlık kontrolü yapıp platforma göre doğru path'i üretiyor (`contact`, `job`/
`job_file`, `task` (soft-delete farkında), `quote`, `product`/`stock`, `personnel`, `trade_document`
kapsanıyor). `activity_render_list()` (web `activity.php` + `dashboard.php` widget'ı) ve
`mobile/activity.php` artık bu resolver'ı kullanıyor, kapsamadığı türlerde eski stored-url
davranışı korunuyor (geriye dönük kırılma yok). Silinmiş/soft-delete edilmiş hedeflerde artık link
yerine "Kayıt artık mevcut değil" gösteriliyor.

**Bilinen kapsam dışı alanlar** (stored-url davranışı bilinçli olarak DEĞİŞMEDİ):
- **Satış/Satın Alma**: DB'de gerçek bir "satış/satın alma kaydı" kavramı yok (`entity_id` aslında
  bir ürün id'si), tekil bir detay ekranı hiç yok — "satış detayına gitmeli" beklentisi **yeni bir
  ekran gerektirir** (yeni özellik, kullanıcı onayı bekliyor, bkz. `ROADMAP.md`).
- **Finans**: `entity_type='finance'` farklı çağrı noktalarında kasıtlı olarak farklı hedeflere
  gidiyor (çoğu `finance.php` listesine, `mobile/collection.php` bilinçli olarak ilgili cariye) —
  tek bir genel kural bunu bozardı, dokunulmadı.
- **trade_document**: mobilde karşılığı (`trade_document_view.php`) hiç yok — mobil kullanıcı da
  web'in mutlak URL'ine yönlendiriliyor (önceden de böyleydi, artık en azından her zaman doğru
  belgeye gidiyor). Bkz. `ROADMAP.md` "Açık — kapsamı netleşmemiş".

Yerel `ots_sectest` QA: personel çapraz-platform senaryosu (yeni + geçmiş kayıt, PASS), cari/iş/
görev/teklif/ürün linkleri (PASS), silinmiş/soft-delete kayıt fallback (PASS), 3 ekranda regresyon
yok. `php -l` 4/4 dosyada temiz.

## UX/STABILITY PATCH-003 — Takvim Günlük Filtre: kod değişikliği YOK, deploy açığı tespit edildi (2026-07-05)
Kullanıcı bildirimi: bugün/yarın için görev oluşturup takvimde bugüne tıklayınca hâlâ tüm günlerin
görevleri listeleniyor. **Bulgu**: kod (JS yok, düz `<a href="takvim.php?...&g=$d">` linki + PHP'de
`$byDay[$g]` filtresi) yerel `ots_sectest`'te gerçek görev verisiyle (bugün/yarın/boş gün, web+mobil,
6/6 senaryo) doğru çalışıyor — reprodüksiyon YAPILAMADI. `git blame` ile asıl düzeltme (ay
ızgarasının seçili olmayan günlerde madde başlığı basmayı bırakması) commit `dd35352` (2026-07-05).
`VERSIONING.md` "Release Durumu" primac.tr'nin hâlâ `d7c593a` (2026-07-04, `dd35352`'den ÖNCE)
referans sürümünde olduğunu gösteriyor — `d7c593a`'daki takvim kodu ay ızgarasında HER günün madde
başlığını koşulsuz basıyor. **Sonuç: primac.tr muhtemelen 2026-07-05'ten bu yana yeniden
yüklenmedi**, kullanıcı hâlâ eski/bozuk kodu test ediyor olabilir — bir "DEV PACKAGE MODE" turu ile
doğrulanmalı. Kod tarafında değişiklik yapılmadı, commit yok.

## SECURITY SPRINT-004 — DEVAM EDİYOR (2026-07-05, FAZ-1 → FAZ-4F + HIGH-RISK CHECKPOINT AUDIT: PASS)
Kapsam: Merkezi CSRF (Cross-Site Request Forgery) koruma altyapısı, aşamalı rollout stratejisiyle
(Seçenek B: JS tabanlı otomatik token enjeksiyonu) uygulanıyor. **Sprint henüz TAMAMLANMADI** —
bu bir ara checkpoint kaydıdır.

- **Altyapı (FAZ-1)**: `boot.php`'ye `csrf_token()`/`csrf_field()`/`csrf_verify()` eklendi (session
  başına bir token, `hash_equals` ile doğrulama, 403 + sabit Türkçe mesaj: "Güvenlik doğrulaması
  başarısız. Lütfen sayfayı yenileyip tekrar deneyin."). `layout_top.php` (web) ve
  `mobile/common.php` (mobil) her sayfaya meta+auto-inject JS ekliyor — mevcut formlara elle
  dokunmadan `csrf_token` hidden input'u otomatik ekleniyor.
- **AJAX desteği (FAZ-2)**: Tüm `fetch(...POST...)` çağrılarına `X-CSRF-Token` header eklendi;
  FormData/JSON `Content-Type` davranışı bozulmadı.
- **Aşamalı enforcement (FAZ-3A → FAZ-4F)**: Sırasıyla pilot (`users.php`, `sil.php`) → Bildirimler
  (`notifications.php`, GET-tabanlı sil/clear POST'a çevrildi) → Finans/Muhasebe (`accounting.php`,
  `finance.php`, `finance_accounts.php`, `checks_notes.php`, `kasa.php`) → Finans işlem ekranları
  (`finance_new.php`, `finance_transfer.php`, `finance_account_view.php`, `payment.php`,
  `collection.php`, `transfer.php`, `account_view.php`, `movement_view.php`) → Personel
  (`personnel_new.php`, `personnel_edit.php`, `personnel_view.php`) → Kimlik/Sistem
  (`sifre_sifirla.php`, `temizle_veri.php`) → Mali belge/Teklif (`trade_document_new.php`,
  `teklif.php`, `quote_approve.php`, `public_file.php`) → WhatsApp (`wa_send_now.php`) → İş/Görev
  (`job_view.php`, `task_view.php`, `work_view.php`). Toplam **29 enforced basename** (bazıları
  web+mobil aynı isimli dosyayı otomatik kapsıyor, ör. `users.php` → `mobile/users.php`).
- **Companion fix'ler** (token üretemeyen, girişsiz/bağımsız `<head>`'li sayfalara minimal
  `csrf_field()` eklenmesi — UI/davranış değişikliği yok): `sifre_sifirla.php` (3 form),
  `quote_approve.php`, `public_file.php`, `mobile/notification_view.php` (GET sil linki POST
  forma çevrildi).
- **IDOR/yetki modeline dokunulmadı**: `job_view.php`/`task_view.php` bilinçli olarak
  `page_module_map()` dışında kalmaya devam ediyor (bildirimden açma tasarımı) — bu konu ayrı
  **SECURITY SPRINT-009 / Privilege Escalation** veya Authorization Audit kapsamında ele alınacak.
- **HIGH-RISK CSRF CHECKPOINT AUDIT: PASS** — enforced liste tutarlı, token üretemeyen sayfa
  kalmadı, AJAX header'ları eksiksiz, geniş GET smoke testinde regresyon yok, orijinal yüksek-risk
  sınıflandırmasının tamamı artık enforced. Kalan **orta/düşük risk grubu** (CRM, Stok/Ürün,
  İş/Görev ana formları, Mesajlaşma/Talep, Satış/Satın Alma) henüz kapsam dışı — FAZ-5'te ele
  alınacak.

Checkpoint commit'ler: `7934805` (SPRINT-003 kapanışı), `90dffa7` (FAZ-4A checkpoint), `a32893c`
(HIGH-RISK CSRF rollout checkpoint), `4708cd6` (FAZ-5A — CRM). Production'a (acanstr.com/ots)
dokunulmadı.

- **FAZ-5A — CRM grubu: PASS (2026-07-05, commit `4708cd6`)** — `contact_new.php`,
  `contact_view.php` `$__csrf_enforced_pages` listesine eklendi (basename eşleşmesi
  `mobile/contact_new.php`/`mobile/contact_view.php`'yi otomatik kapsıyor, ek kod gerekmedi).
  Yerel `ots_sectest` + `php -S` + gerçek HTTP istekleriyle test edildi: token'lı POST (web+mobil,
  `contact_new.php` kayıt oluşturma + `contact_view.php` `save_profile`/`save_contact` güncelleme)
  başarılı, token'sız POST (4/4 senaryo) 403 döndü, GET/liste/detay ekranlarında regresyon yok.
  FAIL yok. Test verisi ve test kullanıcısı izinleri temizlendi, `config.php` diff ile doğrulanarak
  orijinaline geri yüklendi. GitHub'a push edildi (`4708cd6`).

**Sıradaki faz: FAZ-5B — kapsamı henüz netleşmemiş** (kalan orta/düşük risk grubu: Stok/Ürün,
İş/Görev ana formları, Mesajlaşma/Talep, Satış/Satın Alma) — kullanıcı onayı bekliyor.

## SECURITY SPRINT-003 — PASS (2026-07-05, yerel QA MODE ile doğrulandı)
Kapsam: `sifre_sifirla.php` (şifre sıfırlama) brute-force + hedef seçimi kısıtsızlığı nedeniyle
hesap ele geçirme riski. Sadece bu dosya ve yardımcı fonksiyonları değişti — login/session
mimarisine (`boot.php`, `index.php`) dokunulmadı, yeni özellik eklenmedi.

- Yanlış deneme sayacı (`$_SESSION['reset_attempts']`).
- 5 başarısız denemede reset token/kod tamamen iptal ediliyor (`reset_token`/`reset_expires` NULL).
- IP bazlı rate-limit (`send_code` 8/15dk, `reset_pass` 15/15dk) — dosya tabanlı, session'a bağlı
  değil (`reset_ratelimit.json`, git'e girmiyor).
- Aynı kullanıcıya kısa sürede (60 sn) tekrar kod gönderimi engellendi — yeni kolon gerekmeden
  var olan `reset_expires`'tan geriye hesaplanıyor.
- Reset token süresi 30 dk → 10 dk.
- Başarılı reset sonrası `reset_uid`/`reset_show_code`/`reset_attempts` tamamen temizleniyor.
- Enumeration koruması korundu — var/yok kullanıcı mesajı hâlâ birebir aynı, rate-limit/throttle
  mesajları da hesap varlığını sızdırmıyor.

Yerel `ots_sectest` MariaDB + `php -S` + gerçek HTTP istekleriyle 8 senaryo test edildi (geçerli
kullanıcı kod gönderimi, var olmayan kullanıcı mesaj eşitliği, 5 yanlış denemede iptal, iptal
sonrası doğru kodun da reddi, süresi dolmuş kod reddi, başarılı reset sonrası eski kodun tekrar
kullanılamaması, IP/hesap bazlı rate-limit, login akışının bozulmadığı) — **FAIL yok.**
`config.php` test sonunda diff ile birebir doğrulanarak geri yüklendi. Commit/push yapılmadı,
production'a dokunulmadı.

## UX / STABILITY PATCH-002 — DEV QA PASS (2026-07-05, yerel QA MODE ile doğrulandı)
Yerel `ots_sectest` MariaDB + `php -S` + gerçek HTTP istekleriyle (admin ve `edit_delete` yetkili
admin-olmayan test kullanıcısı) 7 maddenin tamamı tek tek test edildi. Sonuç:
- **Son İşlemler routing**: PASS (Cari/Stok/Satış/Satın Alma/Personel/Cari-belge, web+mobil, içerik
  doğrulamalı).
- **Teklif liste/detay**: PASS (Detay linki + admin-olmayan `edit_delete` yetkili kullanıcı gerçekten
  düzenleyip kaydedebildi).
- **Çek/Senet çift kayıt**: PASS (POST artık 302 redirect veriyor, F5 simülasyonunda tek kayıt kaldı,
  takvimde tek görünüyor).
- **Takvim günlük filtre**: PASS (bir gün seçiliyken diğer günlerin başlığı ızgarada görünmüyor,
  sadece rozet).
- **Mobil Mesajlaşma boşluğu**: **CONDITIONAL PASS** — CSS özgüllük düzeltmesi (`body.chat-mode.kb`)
  render edilen sayfada doğrulandı, üstünlüğü matematiksel olarak kesin; ancak bu ortamda gerçek
  tarayıcı/klavye render'ı yapılamadığı için piksel-seviye görsel doğrulama YAPILAMADI —
  **gerçek iPhone Safari cihaz testi bekliyor.**
- **PWA Push**: **SERVER-SIDE PASS** — `push_to_user()` gerçek bir FCM aboneliğine başarıyla
  gönderim yaptı (sunucu 201 "OK" ile kabul etti), yeni eklenen `push_log()` hem başarı hem
  başarısızlık (410 Gone testi) senaryosunda doğru çalıştı. Ancak Safari/iOS'a özgü arka
  plan/kapalı-uygulama teslimatı bu ortamdan (Chrome/FCM aboneliği üzerinden) test edilemedi —
  **gerçek iPhone Safari cihaz testi bekliyor.**
- **WhatsApp**: kapsam dışı bırakıldı (kod değişikliği yok) — gelen mesaj takibi ayrı bir
  **WHATSAPP INTEGRATION SPRINT** olarak ele alınacak (yeni webhook + yeni tablo gerektiriyor).
- **Yan not**: `migrate.php` yerelde çalıştırılınca kendini siliyor (production güvenlik önlemi) —
  yerel QA sırasında silindi, `git restore` ile geri getirildi. **Bundan sonra yerel QA'da
  migrate.php'nin bir KOPYASI üzerinden çalıştırılması gerekiyor**, orijinali üzerinde değil.

FAIL yok. Kod değişikliği bu turda YAPILMADI (sadece test) — commit edilmedi, push edilmedi,
**production'a dokunulmadı** (ayrı "DEPLOY MODE" komutu bekliyor).

## UX / STABILITY PATCH-002 (2026-07-05, DEV — commit edilmedi, primac.tr'ye henüz yüklenmedi)
Son kullanıcı testinde bulunan 7 maddelik kararlılık/navigasyon turu. DB/mimari değişikliği yok.
1. **"Son İşlemler" — çapraz-platform kırık linkler (11 dosya)** — kök neden: birçok
   `activity_log()` çağrısı ya web-mobil arasında farklı isimlendirilen sayfalara (`kasa.php` ↔
   `finance.php`, `personnel_view.php` sadece mobilde) BARE (öneksiz) path ile ya da gereksiz
   `mobile/` önekiyle yazılmıştı — bu, Son İşlemler web'den açılınca mobil ekrana ("mobil başka
   ekrana gidiyor"), mobilden açılınca `mobile/mobile/...` çift path'e (404, "tamamen alakasız
   sayfa") düşüyordu. İki düzeltme kalıbı uygulandı: (a) aynı isimli dosya HER İKİ tarafta da varsa
   (`contact_view.php`, `sales.php`, `purchase.php`, `product_view.php`) `mobile/` öneki kaldırıldı;
   (b) isim ayrışıyorsa (`finance.php`/`kasa.php`, `finance_accounts.php`, mobil
   `personnel_view.php`, `trade_document_view.php`) `base_url()` ile MUTLAK path yazıldı — hangi
   taraftan açılırsa açılsın doğru hedefe gider. Dosyalar: `mobile/product_new.php`,
   `mobile/sales.php`, `mobile/contact_new.php`, `mobile/purchase.php`, `mobile/collection.php`,
   `mobile/transfer.php`, `mobile/payment.php`, `finance_new.php` (×2), `finance_transfer.php`,
   `mobile/personnel_new.php`, `trade_core.php`. Not/Mesaj/Bildirim türleri `activity_logs`'a HİÇ
   yazılmıyor (yeni loglama eklemek yeni özellik sayılırdı, bu tur kapsamı dışında bırakıldı).
2. **Teklif — liste/detay CRUD tutarsızlığı** — liste satırı zaten tıklanabilirdi ama görünür bir
   "Detay" bağlantısı yoktu (jobs.php'deki standart eksikti) → eklendi. Detay ekranındaki "✏️
   Düzenle" (web+mobil, 3 yer) ham `is_admin()`/`$isAdmin` kontrolü kullanıyordu — projede zaten var
   olan kademeli "Düzenleme/Silme Yetkisi" (`can_edit_delete()`) yerine — bu yüzden `edit_delete`
   yetkili ama admin olmayan biri Düzenle'yi hiç göremiyordu (mobilde görüp kaydedemiyordu). Üçü de
   `can_edit_delete()`'e çevrildi. "Sil" bilinçli olarak DOKUNULMADI — paylaşılan `delete_button()`
   (boot.php) tüm modüllerde admin-only olacak şekilde tasarlanmış, teklif'e özel değil, mimari
   değişikliği bu yamanın kapsamı dışında.
3. **WhatsApp — gelen mesajlar görünmüyor (kod değişikliği yok, sadece tespit)** — kök neden:
   entegrasyon (`share_lib.php`, UltraMsg gateway) SADECE gönderim (outbound) yapıyor; hiçbir
   webhook alıcı endpoint'i ve hiçbir konuşma/mesaj geçmişi tablosu yok. Bu bir API kısıtı DEĞİL —
   UltraMsg gelen mesaj webhook'unu destekliyor, sadece bu projede hiç uygulanmamış. Konuşma
   geçmişi göstermek yeni bir webhook receiver + yeni bir tablo gerektirir = yeni özellik, bu
   yamanın kapsamı dışında tutuldu.
4. **Mesajlaşma — liste/composer arası boşluk regresyonu** — kök neden: `mobile/common.php`'de
   `body.chat-mode{padding-bottom:0}` ile `body.kb{padding-bottom:env(safe-area-inset-bottom)}`
   AYNI özgüllükte (tek sınıf seçici) ve `body.kb` kaynak sırasında SONRA geliyordu — kullanıcı
   mesaj yazma alanına dokunduğunda (klavye açılıp `kb` sınıfı eklenince) `chat-mode`'un
   sıfırladığı boşluk `kb` kuralı tarafından geri getiriliyordu. `body.chat-mode.kb{padding-bottom:0}`
   (bileşik seçici, daha yüksek özgüllük) eklenerek kalıcı hale getirildi — artık kural SIRASINDAN
   bağımsız.
5. **PWA Push — Safari'de arka planda bildirim gelmiyor (kod seviyesinde teşhis)** — kök neden KESİN
   olarak doğrulanamadı: `push_to_user()` (push_lib.php) TÜM hataları (`catch(Throwable){return
   false;}`) sessizce yutuyordu, hiçbir iz bırakmıyordu — bu yüzden bugüne kadar teşhis mümkün
   değildi. Loglama eklendi (`push_log()`, `push_debug.log`, `.gitignore`'da zaten `*.log` var).
   İki önceden bilinen, primac.tr SUNUCUSUNDAN doğrulanması gereken ön koşul hâlâ ROADMAP'te açık:
   VAPID anahtarlarının config.php'de gerçek olup olmadığı, gmp/bcmath PHP eklentisinin kurulu olup
   olmadığı — ikisi de bu repodan/yerelden görülemez, kesin teşhis için sunucu erişimi gerekir.
6. **Takvim — aynı çek/senet iki kez görünüyordu** — kök neden BULUNDU: `checks_notes.php` (web)
   `save_cn` işleminde PRG (Post-Redirect-Get) YOKTU — sayfa yenilenince (F5) form yeniden POST
   edilip aynı çek/senedin ikinci kaydı + `checks_notes_auto_create_task()` ile ikinci bir otomatik
   hatırlatma görevi oluşuyordu (bu görev takvime düşüyor, dolayısıyla "aynı çek iki kez" görünümü).
   `mobile/checks_notes.php` zaten PRG kullanıyordu, web şimdi aynı deseni kullanıyor (redirect +
   session flash mesaj).
7. **Takvim günlük detay filtresi çalışmıyordu** — kök neden: `?g=` filtresi altındaki DETAY paneli
   zaten doğru filtreleniyordu, ama AY IZGARASI (takvim.php'nin üstteki grid'i) bir gün seçilse de
   HER günün madde başlıklarını basmaya devam ediyordu — kullanıcı bunu "filtre çalışmıyor, hepsi
   geliyor" olarak yaşıyordu. Bir gün seçiliyken diğer günler artık sadece bir "●" + sayı rozeti
   gösteriyor, madde başlıkları SADECE seçili günde görünüyor.

`php -l` 17 değişen PHP dosyada temiz. Regresyon: checks_notes.php'nin edit_cn/delete_cn dalları
davranış olarak DEĞİŞMEDİ (sadece save_cn PRG aldı), teklif.php'nin Sil'i ve delete_button()
paylaşılan fonksiyonu dokunulmadı, mobile/calendar.php zaten doğru filtreliyordu (değişmedi).

## UX REFINEMENT PATCH (2026-07-05, DEV — commit edilmedi, primac.tr'ye henüz yüklenmedi)
Bir önceki sprintin son kullanıcı testinde yakalanan 4 küçük UX eksiği düzeltildi. DB/mimari
değişikliği yok, tüm değişiklikler mevcut ekran/fonksiyonları yeniden kullanıyor:
1. **`finance.php` "Son Finans Hareketleri" satırları artık tam tıklanabilir** — düzenlenebilir
   satırlar `finance_new.php?id=` (mevcut Düzenle hedefiyle aynı, yeni bir detay sayfası
   eklenmedi) linkine gidiyor; ✏️ Düzenle ve 🗑 Sil kendi davranışında kaldı (`event.target.closest`
   ile satır-tıklamasından ayrıştırıldı). Mobildeki karşılığı (`mobile/payment.php`/`kasa.php`/
   `collection.php` → `movement_view.php`) zaten tam tıklanabilirdi, bu değişiklik web'i mobille
   aynı seviyeye getirdi.
2. **Çek/senet ve finansal hatırlatmalar** — mimari/davranış DEĞİŞMEDİ, sadece ileride ayrı bir
   Hatırlatmalar modülü altında toplanacağı kararı `ROADMAP.md`'ye işlendi.
3. **"Son İşlemler" (activity.php) — mobil parite + kırık/yanlış link düzeltmeleri**:
   `mobile/activity.php`'deki kartlar hiç tıklanabilir DEĞİLDİ (düz `<div>`), web'deki
   `activity_render_list()` deseniyle aynı şekilde `<a>` linke çevrildi. Ayrıca kaynağında
   boş/yanlış URL üreten 9 `activity_log()` çağrısı düzeltildi: `tasks_lib.php`'deki 4 çağrı
   (Düzenleme/Silme/Yorum/Dosya Ekleme) boş url yerine `task_view.php?id=` veriyor artık;
   `public_file.php`'deki müşteri onay/red kaydı boş url yerine ilgili `job_view.php?id=`'e
   gidiyor (zaten fetch edilen `job_id` kullanıldı); "İş Ekle"/"Kendime İş Ekle"/İşlerim durum
   güncellemesi (web+mobil, 6 dosya) artık genel `mytasks.php`/`tasks.php` listesi yerine
   SPRINT-003'te eklenen spesifik `task_view.php?id=`'e gidiyor (task_view.php'nin GET'i zaten
   korumasız/herkese açık olduğu için yetki riski yok). Not/Mesaj/Bildirim türleri şu an
   `activity_logs`'a hiç yazılmıyor (yeni loglama eklenmedi — kapsam dışı, mimari/DB değişikliği
   sayılırdı) — bu yüzden bu turda "kırık" bir Not/Mesaj/Bildirim linki yoktu, sadece mevcut
   Görev/İş türü linkleri düzeltildi.
4. **`mobile/push_enable.php` sadeleştirildi** — Notification API/PushManager/ServiceWorker/
   Standalone gibi teknik teşhis artık SADECE `user_can('users')` (admin/yönetici) görüyor
   (`#env` paneli + adım adım teknik `#log` çıktısı). Normal kullanıcı artık tek, sade bir durum
   mesajı görüyor: başarılı → "Bildirimler aktif. Bu cihaz bildirim almaya hazır.", izin reddi →
   "Bildirim izni verilmedi. Telefon ayarlarından bildirimleri açabilirsiniz.", diğer teknik
   hatalarda (desteklenmeyen tarayıcı, VAPID/sunucu hatası) genel "Bildirimler şu anda
   etkinleştirilemedi. Lütfen tekrar deneyin." Admin için teknik akış davranışı DEĞİŞMEDİ (aynı
   adımlar, aynı hata mesajları).

`php -l` 11 değişen dosyada temiz. DB şeması değişmedi, yeni dosya/route eklenmedi. Henüz primac.tr'ye
yüklenmedi, commit edilmedi (kullanıcı talebiyle bu turda push/release yok).

## SPRINT CLOSE — DEV'de test edildi, PASS (2026-07-04)
Aşağıdaki "LOCAL QA MODE + düzeltmeler" ve "UI/UX İyileştirmeleri + SPRINT-003" paketleri primac.tr
(DEV) üzerinde fiilen yüklenip smoke test edildi, kullanıcı PASS onayı verdi. Bu turda ayrıca DEV
smoke test sırasında/sonrasında bulunan ek maddeler kapatıldı:
- **Komuta Merkezi'ne Takvim kutusu** — kullanıcı üst çubuğa (topbar) eklenen Takvim/Notlarım
  pill'lerinin kullanıcı adı alanını bozduğunu bildirdi; bu ikisi topbar'dan kaldırıldı, Takvim
  bunun yerine `dashboard.php`'nin modül kart ızgarasına ("İşler/Cariler/Finans..." ile aynı stilde)
  eklendi. Işgara 8→9 kutu için 4 sütundan 3 sütuna çevrildi (tam 3x3 oran).
- **Web mesaj rozeti + Web Push bildirimi eklendi** — kullanıcı bildirimi: "uygulama içi mesaj
  bildirimi gelmiyor, web+mobil". Kök neden ikiye ayrıldı: (1) web'de mobildeki `unread_msg()`
  rozetinin bir eşdeğeri hiç yoktu — `layout_top.php`'ye eklendi. (2) Web Push (tarayıcı bildirimi)
  için hiçbir service worker/kayıt kodu yoktu — yeni kök-seviye `sw.js` + `layout_bottom.php`'ye
  kayıt/abonelik scripti eklendi (mobildeki `mobile/sw.js`/`mobile/common.php` deseninin web'e
  uyarlanmış hali, offline cache YOK — sadece push). Gerçek Chromium (Playwright) ile uçtan uca
  doğrulandı: Service Worker aktive oldu, gerçek bir FCM aboneliği oluştu, `push_subs`'a kaydedildi,
  `push_to_user()` ile gönderilen test bildirimi başarıyla teslim edildi. Yol boyunca bulunan bir
  zamanlama hatası da düzeltildi: `register()`'dan dönen kayıt her zaman aktif olmuyordu,
  `navigator.serviceWorker.ready` ile garantiye alındı.
- **Takvim — görev/not linkleri düzeltildi** (web `takvim.php` + mobil `mobile/calendar.php`):
  notlar önceden alakasız `dashboard.php`'ye gidiyordu (web), görevler her zaman genel
  `mytasks.php`/`tasks.php`'ye gidiyordu (spesifik detay değil). İkisi de artık `notes.php` /
  bugünkü İşlerim sprintinde eklenen `task_view.php?id=`'e gidiyor.
- **Takvim — silinmiş görev hâlâ görünüyordu** — her iki takvim dosyasının `tasks` sorgusuna
  `deleted_at IS NULL` eksikti (aynı sınıf hata, personel modülündeki QA bulgusuyla aynı kök
  neden — soft-delete migration 040 sonrası eklenmemiş sorgular).
- **Web takvimde gün tıklama çalışmıyordu** — kullanıcı bildirimi: "bir güne tıklayınca hepsini
  açıyor, sadece ilgili günü göstermeli." Kök neden: web'de gün numarasının hiç linki yoktu (ay
  ızgarası zaten tüm günleri iç içe gösteriyordu, tıklamanın hiçbir etkisi olamazdı) — mobildeki
  `?g=` gün-filtreleme deseni web'e de eklendi: gün numarası artık `takvim.php?ay=...&g=GÜN`
  linki, tıklanan gün ızgarada vurgulanıyor, altında SADECE o güne ait işler/görevler/notların
  listelendiği bir panel açılıyor ("✕ Kapat" ile geri dönülüyor).

Tüm değişen dosyalarda `php -l` temiz, her madde yerel MariaDB ortamında (bir kısmı gerçek Chromium
ile) test edilip doğrulandı, ardından primac.tr'de smoke test edilerek PASS alındı.

## LOCAL QA MODE + düzeltmeler (2026-07-04, DEV — yerel MariaDB'de 7 modülün tamamı uçtan uca test
edildi, 4 bulgu bulunup düzeltildi ve yeniden doğrulandı; primac.tr'de test edilip PASS alındı)
Aşağıdaki "UI/UX İyileştirmeleri + SPRINT-003" paketinin 7 maddesi yerel (primac.tr'ye dokunmadan)
MariaDB test ortamında gerçek HTTP istekleriyle test edildi. 6/7 madde ilk turda PASS, 2 madde
(#3 Satın Alma, #4 Global Arama) bulgu içeriyordu; ayrıca #6 Personel Kart+Sekme'de bir sayaç
tutarsızlığı bulundu. Bulunan 4 sorun düzeltildi ve tekrar test edilerek doğrulandı:
1. **[YÜKSEK, pre-existing]** `stock_lib.php:84` + `purchase.php:73,77` — `mm()` fonksiyonu (sadece
   `mobile/common.php`'de tanımlı) web bağlamında "Call to undefined function" hatası veriyordu, web'den
   HER satın alma denemesi çöküp transaction rollback oluyordu (hiçbir kayıt yazılmıyordu). `money()`
   (boot.php, her iki platformda da her zaman erişilebilir) ile değiştirildi — `mm()` zaten sadece
   `money()`'e delege ettiği için davranış birebir aynı kaldı. Bu, bugünkü sprintten ÖNCE de var olan
   bir hataydı, İşlerim'in yeni "Satın Alma inline ürün ekleme" testine takılarak ortaya çıktı.
2. **[ORTA]** `search.php:291` — görev arama sonuçları artık yeni oluşturulan `task_view.php?id=`'e
   gidiyor (önceden `job_view.php`'ye ya da genel `tasks.php`'ye gidiyordu — İşlerim ve Global Arama
   ajanlarının paralel çalışıp birbirinin yeni sayfasından haberdar olmamasından kaynaklanıyordu; mobil
   taraf zaten doğruydu, sadece web etkilenmişti).
3. **[ORTA]** `personnel.php` (kart sayaçları) + `personnel_edit.php` (Görevler/Takvim sekmeleri, Genel
   sekmedeki sayaç) — `tasks` sorgularına `deleted_at IS NULL` eklendi. Öncesinde soft-delete edilmiş
   bir görev hâlâ "Açık Görev" sayısına dahil ediliyor ve Görevler sekmesinde listeleniyordu (test:
   1 silinmiş görev sayacı 2 gösteriyordu, düzeltmeden sonra doğru şekilde 1'e döndü).
4. **[DÜŞÜK, pre-existing]** `accounting.php:51`, `accounting_lib.php:88`, `mobile/accounting.php:52`
   — `$_POST['entry_date'] ?? date('Y-m-d')` deseni boş string'i (NULL değil) yakalamıyordu, tarih
   alanı boş gönderilirse "Incorrect date value" SQL hatası veriyordu. `??` yerine `?:` kullanılarak
   düzeltildi (boş string de bugünün tarihine/mevcut kayda düşüyor).

`php -l` her 8 dosyada temiz, tüm 4 düzeltme yerel ortamda tekrar canlı test edilip doğrulandı
(hata logları temiz). Detaylı test raporu (7 madde, senaryo/beklenen/PASS-FAIL/risk) bu oturumun
sohbet geçmişinde mevcuttur.

## UI/UX İyileştirmeleri + SPRINT-003 (2026-07-04, DEV — 7 ajanla tamamlandı, yerel MariaDB'de tam
test edildi, primac.tr'de smoke test edilip PASS alındı — yukarıdaki "SPRINT CLOSE" bölümüne bakın)
Kullanıcının verdiği iki ayrı talimat (4 maddelik UI/UX isteği + "SPRINT-003" mimari revizyon isteği)
7 bağımsız işe bölünüp paralel `ots-feature-dev` ajanlarıyla uygulandı:
1. **Üst Menü** — `layout_top.php`'de "Takvim" linki artık Komuta Merkezi/Notlarım ile aynı seviyede
   sürekli görünür (önceden alt menüde gizliydi).
2. **Notlarım Düzenle** — `notes_lib.php::personal_note_update()` + web/mobil modal/inline form,
   `WHERE id=? AND user_id=?` ile IDOR'a kapalı. "Öncelik"/"Hatırlatma" alanları şemada olmadığı için
   eklenmedi (bkz. `ROADMAP.md`).
3. **Satın Alma inline ürün oluşturma** — `purchase.php`/`mobile/purchase.php`, `sales.php`'deki
   mevcut "➕ Yeni Ürün Ekle…" deseni (`ajax_quick_add.php`) yeniden kullanıldı, yeni dosya/migration yok.
4. **Global Arama** — `search_lib.php`'ye 5 eksik modül eklendi (Görevler/Dosyalar/Kullanıcılar/
   Notlarım/Mesajlar), hepsi `user_can()` + prepared statement + IDOR kontrollü (Notlar/Mesajlar
   sahiplik filtresiyle). "Personel aranmıyor" şikâyetinin kök nedeni kod hatası değil, kullanıcının
   modül adının kendisini araması (kayıt eşleşmiyordu) — diğer modüllerdeki "modül adı yazılırsa son
   kayıtlar listelensin" deseni personele de eklendi.
5. **İşlerim ekranı (Düzenle/Detay/Sil)** — yeni `tasks_lib.php` (ortak yetki/iş mantığı) + web
   `task_view.php` (yeni, mobildeki karşılığı zaten vardı) + migration `040_task_edit_detail_soft_delete.sql`
   (`tasks.created_by/updated_by/deleted_at` + yeni `task_comments`/`task_files` tabloları, idempotent).
   Silme SOFT DELETE (`deleted_at`), hiçbir kayıt fiziksel silinmiyor. **Bonus düzeltme**: bu iş
   sırasında `mobile/task_view.php`'deki bilinen IDOR açığı (bkz. `KNOWN_BUGS.md` "Son Çözülenler")
   da kapatıldı — asıl görev bu değildi, yan ürün olarak bulundu.
6. **Personel modülü kart+sekme (SADECE WEB)** — kod incelemesinde "Personel İş Takip Yönetimi" adlı
   menü grubunun aslında personel YÖNETMEDİĞİ, sadece jobs/tasks/production/design gibi şirket-geneli
   üretim sayfalarını içeren yanıltıcı bir etiket olduğu bulundu (ikinci bir personel yönetim ekranı
   YOK). Bu nedenle üretim/iş takip sistemi TAŞINMADI — sadece etiket "İş / Üretim Yönetimi" olarak
   düzeltildi, `personnel.php` kart görünümüne çevrildi, `personnel_edit.php` (bu projede web'de ayrı
   bir `personnel_view.php` yok) sekmeli hale getirildi (Genel/Görevler/Takvim/Mesajlar/Notlar/
   Dosyalar/Performans/Maaş-Avans-Prim/Giriş Hesabı/Hareket Geçmişi) — hepsi var olan sorguların
   personele FİLTRELENMİŞ görünümleri, yeni iş mantığı icat edilmedi. Mobil parite (kart/sekme +
   `mobile/more.php` etiketi) bilinçli olarak bu turun dışında bırakıldı (bkz. `ROADMAP.md`).
7. **Finans bağlam-duyarlı Gider Türü** — "Ne kaydediyorsun?" adımına göre "Gider Türü" listesi artık
   JS ile yeniden kuruluyor (7 adımın literal kataloğu, `finance_lib.php::finance_expense_type_options()`),
   cari/personel alanları sadece ilgili adımda görünüyor. Değer var olan `payment_type` kolonuna
   yazılıyor (migration YOK). Yan ürün olarak 2 bug bulunup düzeltildi: `accounting.php`'de sihirbazı
   tamamen kıran bir JS scope hatası, ve düzenlemede `category_id`'nin sessizce silinme riski.
   **Bilinen sonuç**: bundan sonra sihirbazla girilen yeni giderler `category_id` taşımayacağı için
   `accounting.php`'nin "Grup Özeti" sekmesi/kategori bazlı raporlar yeni kayıtları kapsamayacak
   (eski kayıtlar etkilenmez) — ayrı bir karar/sprint gerektirir, bkz. `ROADMAP.md`.

Tüm 7 iş için `php -l` temiz, dosya çakışması yok. SECURITY SPRINT-001'den TAMAMEN AYRI commit
edilecek (farklı konu, farklı onay/test döngüsü).

## SECURITY SPRINT-001 (2026-07-04, DEV — primac.tr benzeri yerel MariaDB test ortamında uçtan uca
doğrulandı, `d511fad` ile commit edildi — primac.tr'nin KENDİSİNDE henüz smoke test yapılmadı)
`mobile/personnel_view.php`'deki kritik şifre sıfırlama açığı kapatıldı (System Audit'te bulunmuştu,
bkz. `KNOWN_BUGS.md`). Kök neden: `reset_pw` POST işlemi hedef hesabı doğrudan `$_POST['uid']`'den
alıyordu, bu alanın gerçekten görüntülenen personelin (`$id`) bağlı hesabı olduğu hiç
doğrulanmıyordu — `personnel` modül yetkisi olan admin-olmayan bir kullanıcı `uid` alanına başka
bir kullanıcının (admin dahil) id'sini yazarak o hesabın şifresini değiştirebiliyordu. Çözüm:
POST'taki `uid` artık HİÇ kullanılmıyor, hedef hesap DB'den `app_users.personnel_id=$id` /
`personnel.user_id` bağı üzerinden (mevcut `$usr` fetch'iyle aynı OR-mantığıyla) çekiliyor. Ayrıca
artık işlevsiz kalan gizli `uid` form alanı formdan kaldırıldı.

**Politika değişikliği (aynı sprint içinde, kullanıcı kararı)**: DEV testinde kullanıcı, "personnel"
yetkili (admin olmayan) birinin BAŞKA bir personelin şifresini değiştirebilmesinin (kendi yönettiği
olsa bile) kabul edilemez olduğuna karar verdi — "personel personelin şifresini değiştirmez, kişisel
şifre/kullanıcı adı admin kontrolünde olmalı." Bunun üzerine:
- `reset_pw` ve `make_login` (Giriş Hesabı Oluştur) işlemleri artık **admin/yönetici** VEYA admine
  ek olarak yeni **`personnel_accounts`** modül yetkisi verilmiş bir "alt yönetici"ye kilitlendi
  (`boot.php::module_list()`'e eklendi, `users.php`'deki yetki listesinde otomatik checkbox olarak
  çıkar — yeni migration/şema gerekmedi, mevcut `permissions` JSON altyapısı kullanıldı).
- "🔑 Giriş Hesabı" paneli artık sadece bu yetkiye sahip kullanıcılara görünüyor (`$canManageAccounts`).
- "✏️ Bilgileri Düzenle" formu (ad/rol/telefon/e-posta/IBAN/notlar/aktif) bu turda BİLİNÇLİ OLARAK
  değiştirilmedi — kullanıcı "şimdilik dokunma" dedi, bu ayrı bir karar/sprint (bkz. `ROADMAP.md`).

`php -l` ile doğrulandı (her iki dosya). Web tarafında eşdeğer bir `reset_pw`/`make_login` akışı
olmadığı için parite endişesi yok. Yerel fonksiyonel test YAPILAMADI — yerel `config.php` prod
veritabanı bilgisi içeriyor ve yerel MySQL kurulu değil, bu yüzden canlı primac.tr'de kullanıcı
tarafından manuel doğrulanması gerekiyor (bkz. `NEXT_SESSION.md`).

## FINANCE UX REFACTOR (2026-07-04, DEV — checkpoint commit ile kaydedildi, push/release yok)
Ödeme/Gider ve Muhasebe ekranlarında cari/kategori/personel/kasa/ödeme yöntemi karışıklığını
çözmek için "Ne kaydediyorsun?" sihirbazı eklendi (Cari Ödemesi / İşletme Gideri / Personel
Ödemesi / Vergi-SGK / Banka-Kredi-Kart / Araç Gideri / Diğer). DB şemasına hiçbir yeni kolon
eklenmedi — tür bilgisi hiçbir yerde saklanmıyor, mevcut kayıtlardan `finance_lib.php`'ye eklenen
`finance_record_type_info()` ile türetiliyor (bildirim sprintindeki `notif_type_info()` ile aynı
desen). Kapsam: `finance_new.php` + `accounting.php` (web), `mobile/payment.php` +
`mobile/movement_view.php` + `mobile/accounting.php` (mobil) — hepsi sadece gider/ödeme (`direction
='out'`/`type='gider'`) tarafında aktif, Tahsilat/Gelir akışı hiç değişmedi. `personnel_id` alanı
(migration 035'te zaten vardı, sadece Muhasebe ekranında kullanılıyordu) ilk kez Ödeme/Gider
ekranlarına da eklendi. Düzenleme ekranlarında da sihirbaz çalışıyor — eski kayıtlar dolu alanlarına
bakılarak doğru adımla açılıyor. Detay → `memory/features.md`.

## SYSTEM AUDIT MODE (2026-07-04, read-only denetim — kod/DB DEĞİŞMEDİ, commit yok)
5 uzman ajanla (güvenlik, veri modeli, mimari/kod kalitesi, performans, UX/UI) OTS'nin ürün olarak
kapsamlı denetimi yapıldı. 2 kritik/yüksek güvenlik açığı bulundu (`mobile/personnel_view.php`
keyfi şifre sıfırlama, `mobile/task_view.php` IDOR) — `KNOWN_BUGS.md`'ye işlendi. Mimari/performans/
UX teknik borçları (eksik index'ler, FK'siz silme akışları, design token benimsenmesinin çok düşük
olması, yeni UX standardının sadece bildirimlerde uygulanması) `ROADMAP.md`'ye işlendi. Bu denetim
artık her büyük sprint/RC/major sürüm/production öncesi otomatik tekrarlanacak kalıcı bir standart
(`PROJECT_RULES.md` "Sürekli Kalite Denetimi Standardı"). Tam rapor: Artifact + Masaüstü metin
dosyası olarak paylaşıldı.

## UX İyileştirme — "Çalışma Alanı" (2026-07-04, DEV — primac.tr'de test edildi, lokal checkpoint commit edildi; push/release yok)
`layout_top.php`'deki "Aktif Şirket" kutusu incelendi ve tamamen işlevsiz (sahte) bir dropdown
olduğu bulundu — hiçbir session/DB alanına bağlı değildi, seçenekleri sabit metindi. Projede
gerçek bir çoklu-şirket/tenant filtreleme altyapısı hiç var olmadığı için (ACANS/PRIMAC zaten ayrı
sunucu+DB), bu turda SADECE arayüz sadeleştirildi: kutu "Çalışma Alanı" bilgi etiketine çevrildi,
sahte dropdown kaldırıldı, gerçek uygulama adı (`app_config()['app_name']`) statik metin olarak
gösteriliyor. DB/session/route/iş mantığı DEĞİŞMEDİ. Gerçek çoklu-çalışma-alanı mimarisi ayrı,
büyük bir proje olarak `ROADMAP.md`'ye "Workspace (Multi-Tenant) Architecture" başlığıyla açıldı.

## UX SPRINT-001 (2026-07-04, DEV — primac.tr'de 8/8 test PASS, lokal checkpoint commit edildi; push/release yok)
Mobil Bildirimler modülü kart/detay standardına taşındı, mimariye/DB şemasına/API'ye dokunulmadı
(sadece görüntü katmanı). Kapsam bilinçli olarak mobil ile sınırlandı, web `notifications.php`
bu turda değişmedi (ayrı bir sprintte hizalanacak).
- **Bildirim tipi artık DB'siz türetiliyor**: `notifications_lib.php`'ye eklenen `notif_type_info()`
  mevcut başlık emoji ön ekini (📋 Görev, 📨 Talep, 🏭/📦 Üretim, 📊 Rapor, ⚠️ Uyarı, ✅ Onay, 🖼
  Dosya Onayı, 📝 Not, 🌅 Hatırlatma, + ileride için 💬 Mesaj/👤 Personel) ikon+etiket+renge çeviriyor
  — yeni bir `type` kolonu eklenmedi, migration yok.
- **Liste kartı sadeleşti**: `mobile/notifications.php`'de her satır artık renkli ikon rozeti +
  temiz başlık + en fazla 3 satır özet (uzunsa "Devamını gör →" ipucu) + tarih gösteren, TAMAMI
  tıklanabilir tek bir kart. Ham URL hiçbir zaman kartta gösterilmiyor, ayrı "Aç" butonu kaldırıldı.
- **Yeni Detay ekranı**: `mobile/notification_view.php` — tam metin, tarih/saat, tip rozeti,
  "İlgili Modüle Git" ve "Sil" butonları. Tekil `?id=` sorgusu `notif_get_for_user()` ile
  Sprint-001'deki sahiplik kuralının AYNISINI uyguluyor (başkasının kişisel bildirimi
  görüntülenemez) — yeni bir IDOR açmadan tekil-görüntüleme eklendi.
- **Yeni standart UX kuralı**: "Liste ekranı sade kalır, tekil aksiyonlar (sil/git/işaretle) sadece
  Detay ekranında olur" — `PROJECT_RULES.md`'ye eklendi, bundan sonraki TÜM mobil liste ekranları
  için geçerli.

## UI/UX Sprinti (2026-07-04, DEV — lokal checkpoint commit edildi, push/release yok)
Mobil PWA'nın tasarım dili standartlaştırıldı, mimariye/SQL'e/route'lara dokunulmadı (sadece
HTML/CSS/kompozisyon). Kapsam bilinçli olarak `mobile/index.php` + `mobile/common.php` +
`mobile/more.php` ile sınırlandı (detay → `ROADMAP.md` "UI/UX Sprinti — kapsam dışı bırakılanlar").
- **Design token sistemi**: `mobile/common.php`'ye `:root` CSS değişkenleri eklendi
  (`--radius-sm/md/lg`, `--c-accent/danger/success/warn/muted`) — önceden 8/10/13/14/16/17/22/24px
  arası dağınık radius değerleri ve tekrarlanan hex renkler tek merkezi kaynağa bağlandı.
- **Global arama artık toolbar'ın parçası**: `topx()` fonksiyonu her mobil sayfada ~50px
  yükseklikte, tam genişlik, sol ikonlu bir arama çubuğu gösteriyor (mevcut `search.php`'ye normal
  GET ile gidiyor, route/API SIFIR değişiklik). Chat-mode'da (mesajlaşma ekranı) gizleniyor.
  Canlı-autocomplete İNŞA EDİLMEDİ (kasıtlı, bkz. ROADMAP.md) — sadece DOM iskeleti hazır.
- **Ana ekran kart tutarlılığı**: Personel görünümündeki 2 elle yazılmış kart, admin görünümüyle
  tutarlı olacak şekilde ortak `card()` fonksiyonuna çevrildi. Kart yoğunluğu artırıldı
  (min-height 112→104px, padding 15→14px, grid gap 12→10px) — ilk ekranda daha az boşluk.
- **Bildirim test alanı admin'e taşındı**: `mobile/more.php`'nin en üstündeki koşulsuz-görünür
  "Bildirim & Ses" test paneli ve "Bildirim Kur" kartı artık SADECE `user_can('users')` (admin/
  yönetici) görüyor — normal personel arayüzünden kaldırıldı. Ayrıca artık gereksiz olan (toolbar'da
  global arama olduğu için) "Ara" kartı `more.php`'den kaldırıldı.

## Sprint-001 (2026-07-04, DEV — primac.tr'de test edildi, `0ba36da` ile lokal checkpoint commit edildi; push/release yok)
8 hedef modül (İşler, İşlerim, İş Ekle, Kendime İş Ekle, Notlarım, Kendime Not Ekle, Mesajlar,
Bildirimler) tarandı; İşler temiz bulundu, diğerlerinde:
- **Mesajlar — DEV testinde bulundu, kalıcı okunmamış rozet hatası**: `notes_lib.php`'nin
  "Kendime Not Ekle" sırasında kendine attığı iç mesaj `is_read=0` ile oluşturuluyordu, ama mesaj
  kişi listesi kullanıcının kendisini hariç tuttuğu için (kendinle sohbet diye bir giriş yok) bu
  mesaj HİÇBİR ZAMAN okundu işaretlenemiyordu — 💬 rozeti kalıcı olarak şişiyordu. `is_read=1` ile
  oluşturulacak şekilde düzeltildi (kendi yazdığın bir notun kopyası için "okunmadı" uyarısı
  anlamsız). Zaten var olan takılı kalmış satırlar için tek kullanımlık `debug_unread.php` yardımcı
  script'i hazırlandı.
- **Emoji butonu hâlâ taşıyordu**: önceki turda panel konumu düzeltilmişti ama butonun kendisi
  composer'daki genel `.composer button{width:50px}` kuralı yüzünden "😀 Emoji" metniyle taşmaya
  devam ediyordu. Metin kaldırıldı, diğer ikon-only composer butonlarıyla (📎, 🎤) tutarlı hale
  getirildi (`share_lib.php`).
- **Bildirimler — güvenlik açığı kapatıldı**: `mobile/notifications.php`'deki "Okunanları Sil"/
  "Tümünü Sil"/tekil silme sahiplik kontrolü YOKTU, SİSTEMDEKİ TÜM KULLANICILARIN bildirimlerini
  silebiliyordu. Kök çözüm: yeni `user_notification_status` tablosu (migration 039) + yeni
  `notifications_lib.php` — genel (target_user_id=NULL) bildirimler artık HİÇBİR ZAMAN fiziksel
  silinmiyor, her kullanıcının okunma/gizleme durumu kendi satırında tutuluyor, kişisel bildirimi
  sadece sahibi silebiliyor. `mobile/common.php`, `layout_top.php`, `mobile/poll.php`,
  `dashboard.php` artık tek ortak sayaç/liste fonksiyonunu kullanıyor (3 yerde kopyalanmış sorgu
  kaldırıldı). Web'e mobildeki silme/temizleme butonları artık güvenli şekilde eklendi (parite).
  Ayrıca web'deki DB'de hiç var olmayan `type`/`severity` kolon referansı (dead code) kaldırıldı.
  `temizle_veri.php` + mobil karşılığı yeni tabloyu da temizleme listesine aldı.
- **İşlerim**: `mobile/mytasks.php`'deki ham int-cast sorgu prepared statement'a çevrildi (stil
  tutarlılığı, CLAUDE.md kural 2).
- **İş Ekle**: `mobile/task_new.php`'de yanlış etiketlenmiş `activity_log` kaynağı (`jobs.php` →
  `tasks.php`) düzeltildi.
- **Kendime İş Ekle**: `mytasks.php`'de daha önce hiç okunmayan `?ok=1` parametresi artık
  "İş eklendi" mesajı gösteriyor (notes.php'deki mevcut desenle aynı). Ayrıca DEV testinde bulundu:
  personel kaydı olmayan hesaplar (örn. saf admin) için hata mesajı `mytask_new.php` +
  `mobile/mytask_new.php`'de yönlendirici hale getirildi ("Genel Sistem Yönetimi > Kullanıcılar
  bölümünden personel eşleştirmesi yapabilirsiniz") — veri yapılandırması (personel eşleştirme)
  hâlâ kullanıcı tarafından `users.php` üzerinden yapılması gerekiyor, kod bunu otomatik çözmüyor.

## 2026-07-03 (en yoğun gün — çoklu tur)
- **Tek geliştirme ortamı modeli resmileşti**: DEV=primac.tr / PROD(LIVE)=acanstr.com/ots ayrımı
  kondu (`CLAUDE.md`, `PROJECT_RULES.md`, `memory/deploy.md` güncellendi). PROD'a artık SADECE
  "DEPLOY MODE" komutuyla dokunuluyor. `VERSIONING.md` (resmi sürüm dokümanı) oluşturuldu, Release
  (DEV'e paket hazırlama) ve Deploy (PROD'a DEPLOY MODE ile gönderme) süreçleri artık ayrı adımlar.
- **İşlerim/Görevlerim terim standardizasyonu + "Kendime İş Ekle"**: "Görevlerim" ifadesi projede
  her yerde "İşlerim" olarak birleştirildi (mytasks.php, mobile/mytasks.php, mobile/index.php'deki
  yanlış eşleşen kart etiketi düzeltildi). Admin'in başkasına iş ataması artık "İş Ekle" olarak
  adlandırılıyor (`task_new.php`). Yeni: kullanıcının kendine iş ekleyebildiği ayrı bir form
  (`mytask_new.php` + `mobile/mytask_new.php`, `tasks` yetkisi istemiyor). Emoji seçici paneli
  artık mesaj kutusunun ÜZERİNE binmeden (yukarı) açılıyor.
- **Web'de bildirime tıklayınca mobile'a zıplama + hayalet mesaj rozeti + mobil görev yetki açığı**
  (commit `bb8a710`): web'e `mytasks.php` eklendi, bildirim rozeti/kişi listesi sorguları tutarlı
  hale getirildi, mobil görev güncellemesine `personnel_id` kilidi eklendi. Migration 038.
  Ayrıca aynı gün önceki turda: takvime `tasks` (görevler) entegrasyonu, kişisel Not/Görev alanı
  ("Notlarım", migration 037), 5-ajan güvenlik denetimi (job_view.php yazma yetkisi, requests.php/
  activity.php/contact_documents.php IDOR kapatmaları), satış/satın alma sepet + KDV, personel
  yetki senkronu, mobil PWA offline + barkod/QR okutma, çek/senet modülü genişletmeleri, muhasebe
  KDV, kullanıcı/yetki ekranı iyileştirmeleri, stock_movements şema sapması düzeltmesi (commit
  `3137e68`), personel CV/özgeçmiş yükleme (commit `f606cf9`).
- Bu günün tam listesi (30+ madde) → `memory/features.md` üst kısmı.

## 2026-07-02
- Çek/Senet takip modülü (dosya eki + otomatik hatırlatma görevi), Global arama güvenlik düzeltmesi
  + kapsam genişletme, "Düzenleme/Silme Yetkisi" kademeli izin, Finans hesapları/hareketleri
  düzenle-sil, Marka adı yaygınlaştırma + yetki canlı yenileme, Gider kaydında kategori desteği,
  Bildirim `action_url` + mesaj görünürlük onarımı (+ open-redirect kapatma), Muhasebe modülü.

## 2026-07-01
- Sabah raporu + geciken iş sayısı bildirimi.

## 2026-06-30
- Günlük yönetici PDF raporu + talep akışı, 4 ajan turu (cari alanları, modül aktif/pasif, şifre
  sıfırlama, mesaj düzenle), Paylaşım + Teklif + Mesajlaşma onarımı, Teklif PDF + ACANS logo,
  Geciken İş + Teklif Raporu + Takvim + Web Responsive.

## Daha eskisi
Proje kuruluşundan 2026-06-30'a kadar olan temel modüller (auth, CRM, işler/görevler, stok/ürün,
finans, ticari belgeler, mesajlaşma, muhasebe...) migration `001`–`020` ile atıldı — bkz.
`DATABASE.md` tablo envanteri ve `memory/features.md`'nin alt kısımları.

## Referanslar
Tam detay → `memory/features.md`. Şema değişiklikleri → `DATABASE.md`. Açık işler → `ROADMAP.md`.
