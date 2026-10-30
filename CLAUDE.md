# hilstrucking.com: бриф для Claude Code

Сайт перевозчика **HILS TRUCKING LLC** (USDOT 4454223, MC-1755680). Цель сайта: набор
CDL-A Company Drivers и Owner-Operators. Владелец: Ivan Horevoi. Общение с ним на русском.

## Связь с Cowork
- Cowork (отдельный помощник Ивана) делает рекламу Meta, буклеты и карусели. Общаемся через файл
  `My Drive/HILS Cowork/HILS - Claude Code и Cowork.md` на Google Drive info@hilstrucking.com
  (на Mac: `~/Library/CloudStorage/GoogleDrive-info@hilstrucking.com/My Drive/HILS Cowork/`). Основной аккаунт Google Ивана: info@hilstrucking.com.
- Перед работой с сайтом, пикселем или буклетами прочитать его, после работы дописать запись в «Журнал».
- Вся работа идет через бизнес-аккаунт Google info@hilstrucking.com: рабочие файлы, выгрузки и документы класть только
  на Drive info@ (`My Drive/HILS Trucking/...`, файлы для Cowork в `My Drive/HILS Cowork/`), сервисы Google вести под info@.
  В Claude Иван входит под личным gorevoi15@gmail.com: это только вход, на его Drive рабочих файлов не создавать.
- Роли: Claude Code ставит код на сайт (пиксель Meta, GA4, Google Ads); Cowork ведет аккаунты Meta Ads, GA4, Google Ads
  (кампании, ключевые события, связи) и код сайта не трогает.

## Как публиковать
- Ветка `main`. Каждый push в `main` запускает `.github/workflows/deploy.yml`
  (FTPS на Namecheap, `premium363.web-hosting.com`, папка `public_html`). Через ~15 секунд сайт обновлён.
- После правок: проверить (см. «Проверка перед коммитом»), сделать commit с понятным
  английским сообщением и push. Ивану в ответе коротко по-русски: что изменено и что проверено.
- Не коммитить секреты. `config.php` живет только на сервере (в `.gitignore`), правится Иваном
  через cPanel. В репозитории только `config.example.php`.

## Файлы
| Файл | Что это |
|---|---|
| `index.html` | Главная. Один файл: HTML + CSS + JS + все тексты на 3 языках |
| `owner-operators.html` | Отдельная страница для Owner-Operators. Сделана из `index.html`: без раздела Company Drivers, свой hero, свои 4 карточки, FAQ только General + Owner-Operators, в форме заранее выбран Owner-Operator, своя разметка JobPosting (CONTRACTOR) |
| `owner-operators/index.html` | Переадресация `/owner-operators` → `/owner-operators.html` (адрес напечатан на буклете, не удалять) |
| `ru/`, `ua/` | **Не править и не коммитить.** Русская и украинская версии `index.html`, `owner-operators.html` и политики (`/ru/`, `/ua/`). Их собирает `tools/build_i18n.py` из исходников при каждой публикации (шаг в `deploy.yml`) |
| `tools/build_i18n.py` | Сборка `/ru/` и `/ua/`: тексты из `i18nData` прямо в HTML, свои title/description/og (словарь `META` в скрипте), FAQPage на языке страницы, JobPosting только на EN. Падает с ошибкой, если что-то не так |
| `404.html` | Своя страница «не найдено» (EN/UA/RU по языку посетителя), подключена в `.htaccess` (`ErrorDocument 404`) |
| `health.php` | Проверка формы без отправки заявки: config.php грузится, настройки reCAPTCHA/Sheets/Telegram заполнены, Apps Script отвечает, бот Telegram жив. JSON true/false, без секретов. С `?notify=1` при сбое пишет в Telegram (не чаще раза в 6 часов). Запускается каждый день из `.github/workflows/health.yml`; при сбое GitHub шлет письмо владельцу репозитория |
| `tools/privacy.src.html` | Исходник политики конфиденциальности: три статьи EN/UA/RU. Править здесь. `tools/build_i18n.py` собирает из него `/privacy.html`, `/ua/privacy.html`, `/ru/privacy.html`, в каждой только свой язык. Корневой `privacy.html` тоже собранный: не править и не коммитить |
| `send-mail.php` | Обработчик формы: reCAPTCHA v3 → Google Sheets → бот Telegram (`telegram` в config.php) → письмо через Gmail SMTP → (WhatsApp выключен) → журнал. Перед публикацией GitHub проверяет синтаксис PHP |
| `config.example.php` | Шаблон настроек (реальный `config.php` только на сервере) |
| `.htaccess` | Переадресация www/http → `https://hilstrucking.com`, закрытые служебные файлы, заголовки безопасности. Своего `.htaccess` на сервере не было (23.10.2026) |
| `robots.txt`, `sitemap.xml` | SEO. При добавлении страницы добавить ее в `sitemap.xml` |
| `assets/` | `logo.webp`, `logo-white.webp`, `truck-full.webp`, `og-image.jpg`, иконки |
| `assets/booklets/` | Буклеты: `cd-{en,uk,ru}-01..10.webp`, `oo-{en,uk,ru}-01..10.webp`, PDF `hils-{company-drivers,owner-operators}-booklet-{en,ua,ru}.pdf` |

**Важно:** большинство правок нужно вносить **в оба файла** `index.html` и `owner-operators.html`
(шапка, подвал, форма, FAQ, стили, скрипты). Раздел Company Drivers и калькулятор есть только в `index.html`.

## Как устроены тексты (i18n)
- Языки: `en`, `uk` (украинский, в интерфейсе «UA»), `ru`. У каждого языка свой адрес: `/` и `/owner-operators.html` (EN),
  `/ua/...` (UA), `/ru/...` (RU), связаны `hreflang` в `<head>` и в `sitemap.xml`. Язык страницы задает `<html data-page-lang>`.
  Переключатель EN/UA/RU переходит на адрес языка. Английская страница при заходе отправляет на `/ua/` или `/ru/`, если так выбрано
  раньше (`localStorage` `hils_lang`) или таков язык браузера.
- Проверка локально: `python3 tools/build_i18n.py`, потом `python3 -m http.server` из корня репозитория.
- Новый title/description для RU/UA менять в `META` в `tools/build_i18n.py`.
- Все тексты лежат в JS-объекте `const i18nData = { en: {...}, uk: {...}, ru: {...} }` внизу страницы.
  Элемент получает текст по атрибуту `data-i18n="key"` (innerHTML, HTML разрешен), placeholder по `data-i18n-ph`.
- Статичный английский текст в HTML тоже обновлять: его видят Google и посетители без JS.
- Новый текст = новый ключ во **всех трех** языках.
- FAQ: элементы `.faq-item#faq-<id>` с `data-group="general|cd|oo"` (или `"cd oo"`), тексты `faq_<id>_q` / `faq_<id>_a`.
  Вкладки: All / General / Company Drivers / Owner-Operators. На главной по умолчанию General, на странице OO вкладка Owner-Operators.
  Ссылки вида `#faq-pay` открывают нужный вопрос. При изменении FAQ обновить и JSON-LD `FAQPage` в `<head>`.

## Стиль (обязательно)
- Цвета: синий `#004aad`, зеленый `#00bf63`, темный `var(--blue-deep)`. Шрифт Google Sans.
- Русский и украинский: «Вы / Ваш» и «Ви / Ваш» с заглавной; без буквы «ё»; без длинного тире «—», только дефис «-».
- С заглавной на всех языках: **Owner-Operator(s)**, **Company Driver(s)**. В RU/UA с заглавной **Водитель / Водій** во всех формах
  (кроме «водіння»). В английском обычное «drivers» посреди фразы строчное. URL и якоря (`owner-operators.html`, `#owner-operators`) строчные.
- В рекрутинге «Приглашаем», не «нанимаем».
- Ни Company Driver, ни Owner-Operator не «сотрудник» (оба по 1099): не писать «сотрудник», «трудоустройство», «работодатель», W-2. В форме «Формат сотрудничества», варианты «Company Driver (трак компании)» / «Owner-Operator (свой трак)».
- Чтобы термины не переносились: `Owner&#8209;Operators`, `CDL&#8209;A`, `OTR&nbsp;CDL&#8209;A` там, где это важно.

## Факты, которые нельзя менять без Ивана
- **Форма сотрудничества:** и Company Drivers, и Owner-Operators работают по **1099** (independent contractor), не W-2. Налоги из оплаты не удерживаем,
  в начале года форма 1099-NEC. На сайте: FAQ `#faq-1099` (группа General), JobPosting на обеих страницах `CONTRACTOR`.
- **Company Drivers (только Dry Van):** $2,500 в неделю гарантия или 32% от gross, что больше. Gross обычно $8,000-14,000 в неделю.
  Оплата каждую пятницу за прошлую неделю, direct deposit, стейтмент. Без депозитов, escrow, сборов; ELD и страховку у Водителя не удерживаем.
  DAT по желанию $59/мес + налог PA. Техника: Freightliner Cascadia 2021+, Utility 53' dry van 2023+, APU, холодильник, микроволновка, инвертор,
  планшет для логбука, инструменты, ремни и цепи. График: рестарт дома / 3 недели в рейсе и 4 дня дома / 4 недели и 1 неделя дома.
  Требования: CDL-A, 1 год OTR, чистый MVR (5 лет), DOT medical card, drug screen и Clearinghouse.
- **Owner-Operators:** Dry Van, Flatbed, Step Deck, есть грузы Amazon. Smooth Start: 6% (1-й мес) → 8% → 10% → 12% с 4-го месяца,
  процент только за диспетчинг. Gross $8,000-16,000 в неделю. Удержания в неделю: ELD $55, Cargo от $35, Liability от $325, Safety $50,
  IFTA $30, итого от $495. По факту: tolls (BestPass), топливо. По желанию: PrePass $20/мес, парковка $350/мес, Physical Damage от $449/мес,
  DAT $59/мес + налог PA. Топливо TA/Petro, вся скидка до $0.80/гал, топливо финансирует HILS; Pilot Flying J по запросу.
  Оригинальный rate con на каждый груз, факторинг показываем вживую (screen share или на ярде), портала нет. ELD даем, если нет своего.
  Аренда трейлера Utility 53' 2023+, варианты страховки. Без принудительного диспетча.
- **Контакты:** (445) 444-0204 (звонок, WhatsApp, Telegram), info@hilstrucking.com. Офис: 3103 Philmont Avenue, Suite 362,
  Huntingdon Valley, PA 19006. Ярд и мастерская: 7800 Bristol Pike, Levittown, PA 19057. Часы офиса: Пн-Пт 8:00-18:00, Сб 10:00-15:00,
  Вс выходной; диспетч 24/7 на EN/UA/RU. Facebook profile.php?id=61594776546770, Instagram @hilstrucking, Telegram t.me/IvanH15, WhatsApp wa.me/14454440204.

## Что уже сделано и как работает
- **Форма заявки** → `send-mail.php`: reCAPTCHA v3; строка в Google Sheet «HILS Leads» (Apps Script webhook, `google_sheets` в config.php);
  письмо на info@ через Gmail SMTP (пароль приложения, порт 587, запасной 465); журнал `send-mail.log` лежит **вне** `public_html`.
  В заявке также `lang`, `source` (utm/fbclid/gclid/referrer) и согласие на SMS (с текстом, который видел человек).
  Галочки согласия по умолчанию **не отмечены** (TCPA). Уведомления в WhatsApp выключены до подключения отдельного номера к WhatsApp Cloud API.
- **SEO:** Search Console подтвержден через DNS TXT (запись `google-site-verification=F1rVg3…` в Namecheap не удалять). JobPosting на главной
  (Company Driver, CONTRACTOR (1099), с зарплатой) и на OO-странице (CONTRACTOR, **без baseSalary намеренно**: предупреждение в Search Console игнорируем).
  Organization с MC и USDOT, FAQPage, часы в contactPoint. Title и description содержат MC-1755680.
- **Meta Pixel** «HILS Website» `2040876643281119` (портфель 2106528696904129, кабинет 1048868538174693) в `<head>` обеих страниц и политики:
  PageView; ViewContent (`content_name` Company Drivers / Owner Operators, один раз за посещение); Contact (call / whatsapp / telegram);
  Lead только после ответа сервера «success». Передаются только название, язык страницы и тип связи: ни имени, ни телефона, ни email,
  ни возраста, пола, здоровья (кампании Employment). Тег `facebook-domain-verification` статично в `<head>` главной, не удалять.
  Второй пиксель не ставить, иначе события задвоятся. Код пикселя ставит только Claude Code, не Cowork.
- **Google Analytics 4** «HILS Trucking Website» `G-Q3T1RLJQR0`: `gtag('config')` на том же gtag.js, что и Google Ads `AW-18498459696`
  (второй gtag.js не грузить), Google Signals и персонализация рекламы выключены. События рядом с пиксельными: `view_item`
  (items[].item_name = Company Drivers / Owner Operators), `contact` (`method` call / whatsapp / telegram), `generate_lead` (`role`)
  только после «success»; во всех `page_language`. На политике конфиденциальности GA4 нет.
- **Логотип: брендбук HILS v1.0** (Cowork, 30.10.2026; Drive info@: 08 Logo / HILS Brand 2026-10-30, PDF «HILS - Брендбук v1.0»). С 30.10.2026
  на сайте главный логотип брендбука: знак HT в круге + «HILS trucking». Файлы из папки «02 Сайт» без изменений:
  `assets/logo-brand.svg` (синий круг; шапка, меню, политика, 404) и `assets/logo-brand-white.svg` (белый круг; подвал), 381,79 × 100.
  Высота по брендбуку 44px (~168px), ниже 36px нельзя: телефоны ≤340px 40px, десктоп 1100–1199px 40px, 993–1099px 36px (иначе меню
  наезжает). Охранное поле 0,25 высоты круга. Превью ссылки `assets/og-image-brand.jpg` (грузовик + белый логотип; у Cowork есть
  альтернатива og-image.png — логотип на темно-синем). Логотип для Google `assets/logo-brand-mark.png`. Иконки в корне из «02 Сайт»
  (+ `favicon-16x16.png`, `favicon-32x32.png`), прежние иконки HT в `assets/icons-ht/`. Чужие или самодельные логотипы не ставить.
  Кнопки: брендбук просит белый текст на #00BF63, но это 2,4:1 — на сайте темно-синий текст на зеленом, так и оставлять.
  **Откат к варианту 3** (до 30.10): `git revert` коммита «Brandbook v1.0 logo», файлы `logo-hils-v3*.svg`, `og-image-hils5.jpg`,
  `logo-vertical.png`, иконки из `assets/icons-ht/` оставлены.
- **Логотип HT, вариант 3** (до 30.10.2026; бриф от 28.10.2026, файлы на Drive info@: 08 Logo / 2026-10-28 Design System / HT FINAL / Сайт). С 29.10.2026 был на сайте
  горизонтальный вариант Ивана: знак HT, справа «HILS trucking» одной строкой, низ надписи вровень с низом HT, левый край H в «HILS»
  строго на вертикали внутреннего (нижнего правого) угла перекладины T; заглавные HILS = 34% высоты знака, «trucking» (строчные,
  #00803f на светлом) = 80% высоты HILS; «HILS» Google Sans Bold + обводка 51 ед. шрифта (= +1px в шапке, просьба Ивана; края H
  и низ при этом остаются на своих линиях). Зазоры между буквами в HILS и trucking одинаковые: 2,4px при высоте логотипа 48px.
  Файлы `assets/logo-hils-v3.svg` (шапка, меню, политика, 404) и `assets/logo-hils-v3-white.svg` (подвал), 456 × 117.
  Высота: 48px (~187px), на 993–1099px 37px, 1100–1199px 40px, телефоны ≤360px 40px, ≤340px 37px — не упираться в меню и не
  уже 140px по брифу. Превью ссылки `assets/og-image-hils5.jpg`. До этого (коммит 346e483): `logo-hils-arm*.svg`, `og-image-hils4.jpg`. Сборка: контуры из файла Cowork, только сдвинуты и увеличены, без перерисовки.
  Промежуточные версии (в `assets/`, на страницах не используются): `logo-hils*.svg` / `og-image-hils2.jpg` (две строки без обводки,
  коммит 6092b21), `logo-lockup*.svg` / `og-image-hils.jpg` (две строки с обводкой, c882862).
  **Откат к исходному варианту Cowork по просьбе Ивана:** `git revert` коммита с «logo variant 2» (исходное состояние помечено тегом
  `logo-ht-v1`), файлы `logo-horizontal*.svg` и `og-image-ht.jpg` оставлены в `assets/` специально для этого.
  Старый логотип (дуга и дорога: `assets/logo*.webp`, `logo.png`, `og-image.jpg`) в новых материалах не использовать.
- **Кнопки** (30.10.2026, Иван): все кнопки-действия - «таблетка» (`--radius-pill`), высота 55,6px (padding 15px, 1rem, line-height 1.6),
  зеленые #00BF63 с темно-синим текстом; WhatsApp тоже фирменным зеленым (не #25D366). Пара кнопок всегда одной ширины: на первом
  экране два равных столбца, на телефонах ≤560px одна под другой на всю ширину; WhatsApp/Telegram в блоке контактов той же формы и
  высоты. Перед публикацией замерять: равные ширина и высота, равные отступы слева/справа, иконка с текстом по центру (±0,5px).
- **Тёмная тема** (30.10.2026): по умолчанию по настройке устройства; переключатель (солнце/луна, `#themeToggle`) в синей верхней
  полосе между языком и соцсетями сохраняет ручной выбор в localStorage `hils_theme` (если выбор совпал с устройством, ключ удаляется).
  Скрипт в `<head>` ставит `<html data-theme="light|dark">`, меняет `media` у `<source data-dark>` и theme-color. Все правила темы
  пишутся как `[data-theme="dark"] .класс` (не `@media prefers-color-scheme`). Стили в блоке «DARK THEME» в конце
  `<style>` index.html и owner-operators.html (блок одинаковый в обеих страницах, менять в обеих). Цвета через переменные: фоны
  `--page-bg`, `--surface`, `--surface-muted`, `--surface-line`; синий текст `--link`; постоянный темно-синий для полос и текста на
  зеленых кнопках `--navy`; `--blue-deep` в темной теме светлый (заголовки), поэтому для фона его не использовать. Ночной трак
  `assets/truck-night.webp` (Nano Banana, Иван, 30.10) и белый логотип подставляются через `<picture><source media=...>`.
  Новые светлые фоны писать через эти переменные. Политика, 404 и карта Google остаются светлыми.
- **Буклеты:** при замене слайдов или PDF поменять метку `?v=` у всех адресов `assets/booklets/` в index.html и owner-operators.html
  (в HTML и в JS), иначе браузеры покажут старые файлы из кэша. Сейчас `?v=20261030b` (ребрендинг слайдов от Cowork 30.10).
  Карусель из 10 слайдов в разделах Company Drivers и Owner-Operators, слайды и PDF меняются по языку сайта, кнопка «Поделиться»
  (на телефоне системное меню, на компьютере WhatsApp / Telegram / Facebook / копировать ссылку; ссылка с `utm_source=share&utm_medium=booklet`).
- **Прочее:** калькулятор оплаты Company Driver (max(32%, $2,500)), карта Google на языке сайта (`hl=`), кнопки WhatsApp с готовым текстом
  по языку и разделу, подвал (часы, «Проверить в FMCSA», буклеты, иконки соцсетей с фирменным цветом при наведении).
- **Решения Ивана:** защита от копирования текста не нужна (запрещено только перетаскивание картинок); baseSalary для OO не указывать.

## Проверка перед коммитом
1. Открыть страницы локально (например `python3 -m http.server`) и проверить все три языка (EN/UA/RU) и ширину 390px (телефон).
2. В консоли браузера нет ошибок JS. Ссылки и якоря работают, форма проходит валидацию.
3. Если правка общая, она есть и в `index.html`, и в `owner-operators.html`.
4. Новые страницы добавлены в `sitemap.xml`. JSON-LD валидный (JSON.parse).
5. В текстах соблюдены правила стиля выше.
6. **Симметрия и выравнивание (Иван - максимальный перфекционист).** Любые элементы в одном ряду (кнопки, ссылки с иконками,
   карточки, бейджи) проверять не на глаз, а замером в браузере: центры по вертикали (`getBoundingClientRect`) совпадают
   с точностью до 0.5px, одинаковая высота строки и одинаковые отступы, ничего не переносится на вторую строку. Проверять
   на 1440px и 390px и на всех трех языках (длина текстов разная). Типичные причины сдвига: разный `line-height`,
   обертка `display:block` вокруг inline-элемента, `margin-top` у одного из соседей.

## Открытые задачи
- WhatsApp: подключить номер Verizon к WhatsApp Cloud API, шаблон `new_lead_alert` (Utility, 4 параметра: имя, телефон, роль, штат),
  новый постоянный токен, включить `whatsapp` в config.php. Позже агент в WhatsApp для ответов Водителям.
- Google Business Profile: подтверждение по видео (делает Иван).
- Google Ads: когда запустят рекламу, Cowork создает конверсию «Отправка формы» и пишет метку в общий файл; поставить
  `gtag('event', 'conversion', {send_to: 'AW-18498459696/<метка>'})` рядом с `generate_lead` (только после «success»).
- Search Console: заново отправить `sitemap.xml` и запросить индексацию `/ru/` и `/ua/` (делает Иван).
- Лиды из мгновенных форм Meta видны только в Leads Center, в Telegram и таблицу не попадают. Подключать ли их, решает Иван.
- Письма о заявках на info@ не приходят с 13.10.2026. Решение Ивана (24.10.2026): не чинить, заявки приходят в Telegram @HilsLeadsBot и в таблицу HILS Leads.
