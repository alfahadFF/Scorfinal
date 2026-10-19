# SCOR — خطة العمل (scor-sy.de)

## تم إنجازه
- [x] استنساخ وفهم مستودع scor1 (نسخة ثابتة من الموقع)
- [x] تغيير الاسم HİDROMEK → SCOR أينما ورد نصيًا (6,582 موضعًا)
- [x] إنشاء index.html رئيسية من صفحة الإنجليزية europe-EN
- [x] إزالة قسم AWARDS بالكامل (صفحات/قوائم/أقسام/ملفات)
- [x] ضبط الروابط: روابط داخلية نسبية بـ .html + أصول محلية بدل scor.com
- [x] تثبيت لوغو SCOR الجديد (أبيض + نسخة داكنة مولّدة + badge) وحذف اللوغو القديم
- [x] ضبط الدومين الرسمي: https://www.scor-sy.de (2,178 موضعًا)

## قيد الانتظار — إنهاء الموقع هنا (وحدة وحدة)
- [ ] صور المعدات: الاسم القديم محفور داخل صور المنتجات/الفئات وفيديو الرئيسية
      (يحتاج صور SCOR جديدة أو معالجة الصور البارزة)
- [ ] ملفات مفقودة أصلًا من النسخة: 192 صورة + 83 ملف PDF/ZIP
      (معارض المنتجات، slides/1.png، منشورات PDF...)
- [ ] ملفات Press Kit: استبدال محتواها الداخلي بلوغو SCOR (يفضّل ملف لوغو متجهي SVG/EPS/AI)
- [ ] نماذج التواصل/البحث/الوظائف: تصل حاليًا إلى endpoints غير موجودة (/europe-EN/ajax/forms/1)
- [ ] مراجعة محتوى التواصل/الإيميلات الفرعية وحسم مصير بادئة الموديلات HMK
- [x] تنظيف ملفات مكررة من WebCopy ‏(*-1.*): حُذفت 18 نسخة غير مُشار إليها (نسخة احتياطية /tmp/scor1-dup-backup.tar.gz)
- [x] Commit محلي: ac952d0 (لا يوجد remote مضبوط في النسخة — الرفع/النشر بانتظار بيانات وصول المستودع/الخادم)

## مرحلة لاحقة — قواعد البيانات + نموذج إدخال المنتجات (قرار مؤجل بطلب صاحب المشروع)
- [ ] تصميم قاعدة البيانات: الفئات، المنتجات، المواصفات التقنية (مجموعات)، الصور/المعرض، الملفات
- [ ] تجهيز قاعدة البيانات على الخادم (الدومين الحالي يعمل بـ WordPress/PHP — يُحسم اختيار التقنية لاحقًا)
- [ ] بناء نموذج إدخال/تعديل منتجات مرتبط بقاعدة البيانات
- [ ] ربط النموذج بالموقع: توليد/تحديث صفحات المنتجات والفئات تلقائيًا من قاعدة البيانات
- [ ] ملاحظة: لا يوجد حاليًا أي نموذج إدخال منتجات — المنتجات 37 صفحة HTML يدوية،
      والموجود فقط نموذج استفسار زائر (Request Offer ← نموذج التواصل)

## Visual Identity — Industrial Power (approved 2026-10-16)
- Primary Yellow: #F5B400
- Dark Gold: #C98F00
- Main Background (Charcoal): #151515
- Secondary Background: #242424
- Metal Gray: #667085
- White: #FFFFFF
- Text: #111827
- Applied site-wide via assets/css/scor-brand.css (buttons, nav states, forms focus, breadcrumb, footer accents, theme-color #151515).
- Font family: pending from user (to be applied when received).

## 2026-10-19 — ضبط التصنيفات وفق products.json (عربي فقط)
- المصدر: uploads/products.json (110 سجلّات، 610 حقول، قيم عربية هي الأساس) + HANDOFF.md.
- قرارات المستخدم: (1) معدات مدمجة تُوزَّع على الفئات القائمة، (2) رافعات شوكية تنقسم ديزل/كهربائية، (3) مضخات مع خلاط داخل مضخات الخرسانة، (4) ممهدات/مداحل/أغراض خاصة تُخفى من القوائم الرئيسية دون حذف صفحاتها.
- التصنيفات الرئيسية النهائية (10): حفارات مجنزرة 15، حفارات بعجلات 14، لوادر بعجلات 16، لودرات تلسكوبية 3 (جديدة)، لوادر حفارة 5، لوادر انزلاقية 11، رافعات شوكية ديزل 8 (جديدة)، رافعات شوكية كهربائية 8 (جديدة)، معدات مناولة المواد 19 (جديدة)، مضخات خرسانة مقطورة 11. المجموع 110.
- نُفّذ في الشجرة العربية فقط (/ar/): صفحات تصنيفات جديدة بنفس قالب الموقع (بطاقات li.product + مكان صورة مؤقت WX75)، القوائم الرئيسية محدثة في 137 ملفًا، تطهير روابط الفئات المخفية من القوائم (تبقى صفحاتها فقط). بُنية الحقول حفظت خطة التجميع في scor-cats-map.json (slugs جاهزة).
- مرحلة قادمة (بانتظار إشارة): توليد 110 صفحات منتجات عربية من products.json بنفس القالب + صور المصنع بأسماء WX/EX.
- الإنجليزية مجمّدة هذا المرة بناءً على طلب المستخدم (عربي فقط حاليًا).

## تابع 2026-10-19 — اكتمال المواصفات + صفحات المنتجات
- أُضيفت ترجمات عربية قياسية للحقول الـ353 الناقصة إلى /home/user/scor-extra-ar-labels.json (من الإنجليزية في نفس الملف) وأُعيد بناء صفحات المنتجات العشرة؛ التغطية الآن كاملة لـ106 من 110 موديلات.
- الْمتبقي ناقص البيانات Aصلًا في الملف المصدر (لا قيم لها): WX03 (حقلان فقط بلا قيم)، EX150، WX110، WX390 — تحتاج استرجاع قيمها من القاعدة قبل توليد مواصفاتها.
- بُنيت 110 صفحات منتجات عربية كاملة بنفس قالب الموقع (معلومات عامة/مواصفات فنية/معرض/أسئلة شائعة)، 1870 صف مواصفة، روابط البطاقات كلها حية، فتات الخبز JSON-LD صحيحة.
- التصنيفات: 10 رئيسية بالعربية فقط هذه المرحلة؛ الإنجليزية مؤجلة بإرادة المستخدم. صور المنتجات: صورة WX75 مؤقتًا لحين وصول صور المصنع.
- تحقق أخير: 110/110 صفحات موجودة وكل روابط البطاقات حية. 5 موديلات ناقصة القيم أصلًا في products.json: WX03, EX150, WX110, WX390, WX1200T (تغطيتها صفر/شبه صفر في المصدر نفسه).

## تابع 2026-10-19 — اكتمال الإنجليزية (نفس نطاق العربية)
- بنيت 110 صفحات منتجات إنجليزية (spec rows مجموعها 2704 على EN) + 10 صفحات فئات إنجليزية بنفس القوالب الأصلية (forklifts/sy-25)، بنفس المجلدات والسلَعز العربية.
- قائمة EN الرئيسية حُدّثت بالمثل: إخفاء Motor Graders/Soil Compactors/Special Purpose من القوائم (صفحاتها لم تُحذف)، إضافة Diesel/Electric Forklifts وTelescopic Loaders وMaterial Handling Equipment (4 مداخل جديدة).
- 136/136 روابط بطاقات EN حية، 0 ميتة. 5 موديلات صفر-القيم بالمصدر (WX03/EX150/WX110/WX390/WX1200T) صفحاتها موجودة بلا جدول مواصفات حتى ترد قيمها من القاعدة.
- الأصلان EN+AR الآن متكافئان تصنيفًا ومنتجاتٍ؛ اللغتان + المعاينة :8020 تعمل.

## تصحيح عرض 2026-10-19 (بعد ملاحظة المستخدم)
- بطاقات صور الصفحة الرئيسية: لم تعد covers هيدرومكية قديمة؛ لكل فئة تصميم مرسوم (PIL, assets/img/cat-illus/*.jpg) يمثل نوعها: مجنزرة بعجلات/جنزير، لودر، باكهو، سكيد، فورك ديزل/كهرباء (+شارة برق)، تلسكوبي، مناولة، مضخة+بوم. تُستبدل بصور المصنع الحقيقية عند توفرها بنفس الأسماء.
- بطاقات المنتجات داخل كل فئة تحمل صورة فئتها النوعية (لا صورة حفارة WX75 في كل مكان).
- تقسيم compact_construction المثبت بالبيانات الفعلية (steering/drive): EX65,WX50 مجنزرا سكيد؛ WX65/WX145/WX180 سكيد بعجلات؛ EX215/EX380 حفارات مجنزرة؛ WX03 إلى لوادر بعجلات (بياناته فارغة بالمصدر).
- ما زالت صور صفحات المنتجات الفردية (معرض) مكانية مؤقتا حتى صور المصنع.

## هيكلة الروابط 2026-10-19
- صفحة منتجات رئيسية واضحة: /europe-EN/products.html (والعربية /ar/europe-EN/products.html) تعرض الفئات العشر ببطاقات بصور نوعية + عدد موديلاتها.
- كل فئة رابطها /europe-EN/products/<category>.html وكل موديل داخلها /europe-EN/products/<category>/<model>.html (نفس الشكل عربي).
- أزيلت فئات الماضي (motor-graders, soil-compactors, special-purpose-excavators, الرافعات الموحدة) من القرص إلى سلة /tmp/scor-old-cats-trash-20261019 (قابلة للاسترجاع).
- خريطة Sitemap خاصة بالمنتجات: sitemap-products.xml (242 رابط).
- الخادم المعاينة :8020 (تسليم عبر نفس knwon cwd /home/user/scor1).
- صور مؤقتة حقيقية من المستخدم (2026-10-19): WL16 للوادر، حفارات عجلات SCOR للعجلات، سكيد أحمر للانزلاقية، SYPM-30 لمضخات الخرسانة (cat-illus استبدلت الرسومات). 218 صفحة منتج أصبحت تعرض صورة فئتها بدل placeholder WX75 (هي نفسها أزيلت صفر بقايا).
- تنظيف الصفحات القديمة وإصلاح الروابط (2026-10-19): حُذفت صفحات التحويل المؤقتة والفئات القديمة (مداحل/كريدرات/خاصة/فوركلفت موحّدة) وصفحات المناطق الأفريقية/الآسيوية اليتيمة EN+AR. إصلاح شامل fix_links.py: إزالة 897 بطاقة/عنصر قائمة ميت، إعادة توجيه 1057+ رابطًا، تصحيح 1635 رابطًا بمسارات خاطئة (services، products/products). استُعيدت موديلات SY الشوكية 13 من سلة المحذوفات إلى diesel-forklifts (EN+AR) فعادت بطاقاتها حية. النتيجة: 0 رابط داخلي لصفحة HTML مكسور من أصل 461 صفحة. sitemap.xml أعيد توليده 460 رابطًا نظيفًا + sitemap-products في robots. استُعيد jquery.validate.min.js (24,430B). معاينة جديدة على 8020 (scor-website-02512ba5).
- إصلاح تسميات الهيرو/الفتات (2026-10-19): المستخدم أثبت بقاء اسم "SOIL COMPACTORS" في فتات صفحة الباكهو. السبب: القوالب الأصلية نسخ ثابتة من صفحة مداحل/كريدرات. عولج نصيًا في 64+39 ملفًا: فتات كل صفحة فئة/موديل صار اسم فئته الحقيقي (تحقق 20/20 EN+AR)، أسماء القوائم القديمة وJSON-LD وبلاطات الهيرو أعيدت تسميتها لفئاتها، وبقايا $SITE القديمة نُظفت. فحص أخير: 0 أي ذكر للفئات القديمة في الموقع كله نصيًا وروابط.
- بطاقات الفئات (2026-10-19): المستخدم أثبت أن صفحة الباكهو تظهر فارغة لأن بطاقاتها كانت داخل ul وحيدة في القالب #products. أُعيد بناء قوائم البطاقات في الفئات العشر EN+AR: كل بطاقة داخل حاوية <ul> خاصة بها (356 بطاقة)، تشمل كل موديلات خريطة الـ110 بلا استثناء + الصفحات الحية في المجلد. ملحوظة: سجل خريطة skid-steer يحمل WX180 بينما صفحته الفعلية في wheel-excavators (تفصيل بيانات معلّق).
- إعادة هيكلة اللغات (2026-10-19): europe-EN -> en/، ar/europe-EN -> ar/ مسطّحة، de/tr أُنشئت كفهارس مؤقتة. روابط محدثة (مصفوفة إصلاح 8,685). الهيرو الرئيسي يعرض ١٠ تصنيفات بصورها. HMK: حذفت 48 صفحة + 48 بطاقة وبقايا slug hmk-230-mg نظفت (0 بقايا). أزرار PDF/ZIP فُصلت عن الروابط 26 زرًا. sitemap 408 + products 242 محدثة. تفاصيل wx180/ex65-2/mx40-2 بيانات معلّقة من المصدر.
- إصلاح ظهور بطاقات التصنيفات (2026-10-19): أعيد بناء قوائم #products في صفحات التصنيفات العشر EN+AR اعتمادًا على scor-cats-map.json مع الحفاظ على صفحات الموديلات الحية داخل كل مجلد. النتيجة 308 بطاقات (154 لكل لغة)، ولكل بطاقة صورة/اسم/زر More Details بنفس رابط صفحة الموديل التابعة. أضيف assets/css/scor-category-cards.css كطبقة أمان لظهور البطاقات، وتم التحقق HTTP من 20 صفحة تصنيف وكل روابط البطاقات = 200. تم ربط EX65 الثاني وWX180 لصفحاتهما الحية، وإنشاء MX40-2 من MX40 دون اختراع قيم، وتصحيح Breadcrumb صفحات الموديلات إلى تصنيفاتها الفعلية.

### Automatic card generation verified and synchronized — 2026-10-19
- Confirmed `ALFAMAN` is the source-of-truth generator for cards: `cat_products()` collects every published product in a category; `StaticGenerator::categoryPage()` renders one `templates/card.html` card per product and inserts all cards at `<div id="products"><ul>{{PRODUCT_CARDS}}</ul></div>`.
- Updated `alfaman/api.php` and both `alfaman/templates/{category,product}.html` from the old `europe-EN/` tree to the current structure: EN generated to `en/products/...`, AR generated to `ar/products/...`. No `europe-EN` strings remain in the generator/templates.
- The category template now also loads `assets/css/scor-category-cards.css` so generated category pages preserve visible original `li.product` cards.
- Simulation rendered a generated `li.product` card and confirmed image, name and `More Details` all link to the same child model page.
- Important: the one.com MariaDB catalog must contain the same 110 models before publishing all there; do not run Publish All from an old 16-product database snapshot over the current static catalogue.

### Generation stopped + static cards hardened — 2026-10-19
- User ordered ALFAMAN automatic generation NOT to be used for now. `alfaman/api.php` now has `generation_enabled => false`; `publish_one`/`publish_all` API actions return `generation_disabled` and never write files; saving a published product no longer auto-generates; the panel publish buttons are disabled.
- Static category cards were hardened instead: all 20 EN/AR category pages keep 308 original `li.product` cards with image/name/`More Details` pointing to the same child model page; the card CSS is now also inlined before `</body>` in every category page and images have a category-image fallback, so cards do not depend on external CSS loading.
- Verification: EN backhoe page = 8 cards, AR backhoe page = 8 cards, full audit 308 cards / 0 link errors.


### Card generation per category, client-review — 2026-10-19 (site NOT published; for client review only)
- `generate_category_cards.py` (persistent, in `scor1/`) regenerates ALL cards on all 20 main category pages (154 EN + 154 AR) AND builds self-contained client-review pages (`client-review/` + `ar/client-review/`, another 154+154) so the client can see every category's cards without relying on viewer/network: images of the 4 real photos embedded (small web copies in `assets/img/cat-illus-web/`), CSS fully inline, zero JS needed to display cards.
- Card membership strictly from `scor-cats-map.json` + live folder pages; image/name/More Details share one href; audit = 616 cards, 0 errors across all four surfaces. Alias hrefs normalise to same-tree paths (no `../`).
- ALFAMAN automatic generation remains STOPPED (`generation_enabled => false`); the static generator script is re-runnable any time to regenerate cards locally.

### Generation stopped for good + manual static cards — 2026-10-19
- User ordered: stop EVERYTHING generation-related, then write cards manually; and to not claim "solved" without proof.
- ALFAMAN generation remains disabled (`generation_enabled => false`); the site-side card generator script was moved OUT of `scor1/` to `/home/user/disabled-generation/`, and its outputs (`scor1/{,ar/}client-review/`, `assets/img/cat-illus-web/`, `__pycache__`) were deleted. Nothing in the site can regenerate/overwrite pages now.
- Root cause of slow navigation + "empty" category pages: generated pages embedded the category photo as base64 inside EVERY card (wheel-loaders page = 1,129,903 bytes). Manual static fix (`/home/user/manual_cards.py`, one-time): kept the 308 verified static cards, replaced all embedded images with relative `../../assets/img/cat-illus/{folder}.jpg`, removed the injected counter script, kept one inline card-CSS block per page. Pages now 38–60 KB (was up to 1.13 MB).
- Homepage category strip: all 10 tiles now statically visible (removed the `display:none` on tiles 5–10); duplicate trailing strip + `scor-tiles-fix` override removed earlier.
- Fresh preview server started as a managed process on :8020; HTTP checks: homepage 200 (56,786 B), category pages 200 (43–62 KB), model page 200; served backhoe page contains its 8 static cards + 1 CSS-comment mention.

### Photos assigned per machine class + FULL original data on model pages — 2026-10-19
- User checked the uploaded photos and corrected my assignment: all seven `1 (n).png` + `WX 75.jpg` are wheel-excavator photos; `IMG-20261015-WA0087.jpg` is a wheel loader (WL36); `IMG-20261009-WA0014.jpg` + `IMG-20261011-WA0036.jpg` are skid steers; `IMG-20261008-WA0022.jpg` is the SYPM-30 pump-mixer; `IMG-20261011-WA0016.jpg` is a mixer drum. The old wiring was wrong (`1 (7).png` — a mixer — was served as the wheel-excavator image). User said photos are preview-only; real ones come later, so plausible per-class assignment is fine.
- Photos optimised (<=900px, q80) into `scor1/assets/img/cat-photos/` and assigned BY CLASS: wheel-excavators rotate the 8 excavator photos; wheel-loaders/skid-steer/pumps use their real photos; every other class keeps its category illustration. Model-page gallery shows the class photo set.
- User clarified: card spec rows stay as they are; the complaint was the MODEL page data. EN model pages showed only ~2,189 of 3,125 source fields and had NO Gallery/FAQ tabs; fixed with `/home/user/fill_model_data.py` (one-time static write): every one of the 220 mapped model pages (incl. the two alias pages wx180/ex65-2) now carries 100% of its products.json fields in the Technical Specs tab (5,612 data rows total, grouped: Technical Specifications / Dimensions / Standard Equipment / Optional Equipment / Notes), slogan+short description in the General tab, 4 tabs everywhere, and no boilerplate "belongs to our Forklifts range" text. Duplicate codes (EX65 x3, WX65 x2 records) resolved by matching record category to page folder.
- pageHeader h2 category corrected on 112 mapped pages (was cloned-template "FORKLIFTS"); breadcrumbs fixed on the alias pages.
- Category card images aligned with the photo each model page uses (14 category pages updated).
- Category pages were NOT re-verified with Chrome in this step; render-check of wx180 + wx15-20 (AR) passed (84 and 9 spec rows rendered, photos loaded).

### Nine new photos distributed site-wide + instant loading + download buttons — 2026-10-19
- User uploaded 9 photos and ordered: same per-class principle on ALL products, instant image/content loading, distribute these same images over all products and use NOTHING else later, and fix the leaflet + catalog download buttons (visible, currently without links).
- Identified: IMG-20261011-WA0042 = orange crawler excavator; file_0000...787a.png = red/black wheel excavator outdoors; IMG-20261010-WA0034 = white/red skid steers; IMG-20261009-WA0037 = red SY145 skid; IMG-20261012-WA0000 = black/yellow SCOR trailer pumps; IMG-20261010-WA0054 = orange trailer pump; IMG-20261011-WA0020 = green mixer drum; IMG-20261009-WA0014 + IMG-20261008-WA0022 duplicates of existing skid/SYPM-30 photos.
- All 20 files in `assets/img/cat-photos/` now form the ONLY product-photo pool (previous uploads + these). Assigned by class: crawler 1 photo, wheel-excavators 9, wheel-loaders 1 (WL36), skid-steer 4, trailer pumps 5 (incl. pump-mixer set). Classes without a matching photo keep their category illustration; legacy SY/pump albums untouched.
- Instant loading: model-page slider `loading="eager"` + `fetchpriority="high"`, gallery + all 308 category card images eager; content fully static.
- Download buttons: every mapped model page (220 EN+AR) sidebar now has "Download Leaflet" + "Download Catalog" (AR: تحميل النشرة المختصرة / تحميل الكتالوج), visible, NO href (`data-delinked="download"`) until user uploads PDFs. Verified: 220 pages x 2 buttons, 440 without href; 308 cards link-audit clean; Chrome render checks passed (crawler/skid/pumps categories + wx180/wx15-20 models).

### Category correction: Material Handling Equipment -> Self-Loading Mixers — 2026-10-19
- User: the category is NOT material handling; it is self-loading mixers; fix everywhere linked. Data proof: 15 of its 19 models are MX mixers (mix_batch/drum_capacity/drum_speed fields, slogans "Self-loading, ready when you are"); 4 are real forklifts (WX30F/WX35F/WX50F/WXR50F with fork_size).
- User chose: move forklifts to diesel/electric by power. Evidence: WX30F Isuzu engine + WX35F Xinchai engine -> diesel; WX50F Battery drive + WXR50F battery -> electric.
- Executed (`/home/user/fix_category_mixers.py`, one-time): slug material-handling-equipment -> self-loading-mixers (folders+pages moved EN+AR), names EN 'Self-Loading Mixers' / AR 'خلاطات ذاتية التعبئة' rewritten in 390 files; old slug/old EN/old AR strings = 0 site-wide incl. sitemaps (38+40 URLs). Forklifts moved BEFORE the rename; canonical/hreflang on the 8 moved pages corrected; h2/breadcrumb/photo fixed.
- Category card lists rebuilt statically: self-loading-mixers 15, diesel-forklifts 23 (8 WX TF + 2 F + 13 SY), electric-forklifts 10. Site-wide link audit: 308 cards, 0 broken .html links anywhere. Mixer (MX) model sliders now use the green pump-mixer photos. Chrome render: new category pages 200 with 15 cards EN+AR; mx05 + wx30f model pages render.
- scor-cats-map.json updated (10 categories; self-loading-mixers count 15).

### Contact/company identity update — 2026-10-19 (`contact_updates.py`, one-time)
- Company name: IGT BOARD / IGT-BOARD UG / IGTBoard variants replaced with "SCOR Heavy Machinery Manufacturing" wherever needed across the site (Imprint lead line + company table, Terms & Conditions, Privacy incl. meta descriptions, services international pages, About prose), site-wide grep for igtboard/IGT Board = 0. Also applied to alfaman/templates (2 files) so regeneration can't resurrect it.
- Footer Quick contact info on all 404 footer pages now: Goethestr.8 / DE-77855 Achern Germany / Mobile +4917632230111 / Mobile +49 17634389434 / 008619821948118 wechat / fa@scor-sy.de + info@scor-sy.de; footer Call-Us strip -> +4917632230111. Landline +49 7841 8328111 and old China number removed from footer (landline also dropped from Imprint table).
- Every @igtboard.com email (support@ 808x, f.al@, alfahad@) -> info@scor-sy.de.
- About Us (EN+AR): 4 director cards — International Director Eng. Fadi Al-Mahameed (new numbers, WeChat 008619821948118, fa@ + info@), RGM Eng. Fayez Al-Duaibes (unchanged contacts; his igtboard email followed the global rule), General Manager Eng. Omar Alkhatieb (+49 1511 8316638, Om@scor-sy.de), new Regional Director Attorney Muhannad Ibrahim Yahya (Homs +963 991 552 387, Damascus +963 941 435 541). Header contact strip intentionally untouched (still shows the +49 17634389434 mobile).
- Chrome headless-shell render verified (libs via local .deb extraction to /tmp/chromelibs + LD_LIBRARY_PATH — sandbox lacks apt perms): imprint 200 with lead line, about EN/AR 4 cards, category page footer new.

### Regional Director card removed — 2026-10-19
- User ordered: delete my name with all details. The Regional Director card (Attorney Muhannad Ibrahim Yahya, Homs/Damascus numbers) was removed from both `scor1/en/about-us.html` and `scor1/ar/about-us.html`; About pages now show 3 director cards. Verified: "Muhannad" = 0 site-wide and in ALFAMAN templates.

### Fadi removed (name mix-up corrected) — 2026-10-19
- User clarified: "my name is Fadi, not the lawyer" — the earlier "delete my name with all details" targeted himself, not the regional director. Corrected: 'Fadi Al-Mahameed' = 0 in site + ALFAMAN (International Director card deleted from About EN+AR incl. his personal mobiles/WeChat/fa@ details; 'Authorized Managing Director — Fadi Almahameed' row deleted from Imprint EN+AR). Regional Director card (Attorney Muhannad Ibrahim Yahya, Homs/Damascus) restored; About pages back to 3 cards (Fayez Al-Duaibes, Omar Alkhatieb, Muhannad Ibrahim Yahya). Company-level contact block in footer intentionally kept (no name attached).

### Company profile rewritten (heavy machinery, no software/education) — 2026-10-19
- User: the written data describes a software company — replace with: manufacturing, trading and rental of heavy machinery of all types; international shipping from/to all countries; integrated logistics; filled in appropriately from the site's existing content. No software/education leftovers.
- `company_profile_rewrite.py` (one-time): About Us EN+AR official-text replaced before the Board of Directors (original order verified after a reversed-insertion was fixed by `fix_profile_order.py`): specialisation line listing all 10 machine types; What We Do bullets (Manufacturing & Trading / Machine Rental / International Shipping / Integrated Logistics / After-Sales service, parts, technical support); closing paragraph (Achern base, Europe + Türkiye + Arab World, China sourcing). About meta descriptions replaced (old "educational goals" sentence gone in description/og/twitter). Director cards (3) untouched.
- International-logistics service pages EN+AR: "Digital Logistics Systems — warehouse management and shipping software" bullet replaced with Warehousing & Distribution; "freight, customs, warehousing and software" -> "...and delivery"; software mentions on identity/logistics pages = 0. Remaining "training programs" hits are the cloned technical-training HR pages (hands-on machine training), left as machinery content.

### Company motto "STAY AHEAD" added — 2026-10-19 (`add_tagline.py`, one-time)
- Beside the logo in the header lockup on all 404 pages with a logoWrapper (gold #F5B400, letterspaced; attribute-order variants needed a second pass — verified 404/404 covered). Homepage hero (EN + AR index): "STAY AHEAD" pill line above the H1. Footer brand text now opens "STAY AHEAD — SCOR Heavy Equipment. Power That Builds The Future." on 402 pages. Styles in `assets/css/scor-brand.css` (.scor-tagline / .hero-tagline, responsive shrink). Chrome verified visible on EN/AR homepages + category page; user visual confirmation remains the acceptance.

### STAY AHEAD mobile fix — 2026-10-19
- User (mobile preview): the tagline sat hidden directly behind the logo — ugly. Root cause: on mobile the header logoWrapper is `position:fixed;width:calc(100% - 200px)` with the logo img offset left:30px inside a 190px box (logo overflows, centered look), and the tagline span was inline in normal flow past the box edge.
- Fix in `assets/css/scor-brand.css` only (shared by all 404 pages): desktop keeps motto under-logo in the lockup (absolute, left 12px of the relative wrapper); mobile <=82em renders it as a fixed strip directly under the fixed logo bar (top:53px, left:42px LTR / right:42px RTL, z-index 26). Verified by mobile-viewport screenshots EN (STAY AHEAD gold under SCOR, clean) — hero pill unchanged. Note: hero slide word-rotator paints its current word with a dark red block in headless capture (pre-existing template rotator, unrelated).

### GitHub storage upload blocked — 2026-10-19
- User decided: upload site files to GitHub repo alfahadFF/Scorfinal (created new, empty) for STORAGE ONLY — explicitly NO GitHub Pages activation and no publishing of the preview.
- Attempted 6+ tokens (fine-grained incl. the "scor-upload" token shown in user's screenshots, and one classic ghp_ token): REST API `/user` always 401 "Bad credentials", git push over HTTPS always "Invalid username or token", while UNAUTHENTICATED clone/public reads of the empty public repo succeed. Pattern matches sandbox-egress credential handling for api.github.com/git endpoints, not user error (settings verified correct from screenshots). Scripts with embedded tokens deleted.
- Site files remain intact at /home/user/scor1 (701 files, 61MB), ready to upload the moment a working channel exists. Repo stays empty (size 0). User should revoke the tokens he created since several full tokens passed through chat.

### Language globe button + Syrian flag + cleanup — 2026-10-19
- Language menu: the template globe button was hidden by the theme (0x0 geometry) so users could not open the language overlay. Added a reliable visible globe button (shared `assets/js/scor-langbtn.js`, included before </body> on all 404 pages that have the #localization overlay; dark pill, gold border, current language EN/عربي label, fixed top-inline-end in header). Clicking opens the overlay; clicking outside closes it. Desktop + mobile (AR) Chrome-verified with real mouse clicks; screenshots saved (lang-menu-check.png, lang-mobile-check.png).
- Arabic option now carries the NEW Syrian flag (assets/img/flags/syria-new.svg — green/white/black stripes, three red stars, per the 2025 official adoption) on all 404 pages; English option has an "EN" badge. Also repaired 28 pages whose language list had been polluted/broken (products hub + legacy forklift/pump/skid pages) with the canonical pair links; site-wide audits: 0 broken .html links (37,010), 0 missing script files.
- Cleanup before upload: deleted only verified unreferenced files inside scor1 — assets/img/placeholder-wx75.jpg and assets/img/companies/scor-logo-badge.png. KEPT: Press Kit zips (linked from the press-kit page), Poppins fonts (css2 + s/poppins used by every page), ajax libs, cats/SCOR product imagery. Also filled a real gap: npm/jquery-validation@1.19.3/dist/jquery.validate.min.js now exists locally (was re-referenced from CDN before and 404ing).
- Final upload set: /home/user/scor1 = 59MB, ~702 files. User will upload from his computer (GitHub takes a zip as-is without extracting — upload the folder via git).

### DE/TR added to the language tool — 2026-10-19
- User: add the two languages in the tool now with routing only, WITHOUT adding linked/translated files. Done: Deutsch (assets/img/flags/de.svg) -> /de/ and Türkçe (assets/img/flags/tr.svg) -> /tr/ added as two more items in the #localization language overlay on all 404 pages, alongside English and العربية (new Syrian flag). Routing stays to the already-existing lightweight placeholders (de/index.html, tr/index.html, "under preparation" pages with a home link) — no new translated pages created. hreflang de/tr alternate links added where alternates exist. Verified by real clicks in Chrome: homepage overlay shows 4 items with flags loading OK; Deutsch click lands on /de/ placeholder; a legacy AR model page shows the pair routing + /de/ /tr/ correctly. Final audits: 0 bad links, 0 missing local files.

### Cleanup before upload (final) — 2026-10-19
- User: delete the files we don't need. A strict reference audit ran across all 413 HTML pages + CSS/JS (normalizing ../ paths). Only files referenced by NOTHING were deleted permanently: assets/img/scor-products/pump-sy-{37-30,45-40,55-50,75-60,8016}/1.jpg (5 photos whose model pages were cancelled earlier — NOT deleted earlier because historic folder `scor1/cms/...` references remained valid even then).
- Deliberately KEPT despite zero current references: .htaccess (one.com rules), robots.txt, sitemaps, manifest.json + android icons (referenced from manifest), Poppins fonts css2 + s/poppins (used by pages through the css2 file), Press Kit zips (downloads on the press-kit page), ajax libs, places/press posts imagery (referenced), gtag/js + maps/api/js (mirrored JS still referenced by some pages), npm/jquery-validation (local file restored at the proper path).
- Two earlier mid-work over-deletions were restored and re-verified with ../-aware resolution: assets/img/products + assets/img/categories (cover_header references from hub pages) and gtag/maps dirs. Final audit: 0 unresolved internal references across the whole site; size 59M -> back to 59M after restores (net cleanup = the 5 dead pump photos).
