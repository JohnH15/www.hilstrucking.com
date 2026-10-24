#!/usr/bin/env python3
"""Build the Ukrainian (/ua/) and Russian (/ru/) versions of the site.

The source pages (index.html, owner-operators.html) hold all three languages in
`const i18nData`. Search engines only index the English text there, so for each
source page this script writes ua/<page> and ru/<page> with:
  - every data-i18n / data-i18n-ph text already rendered in that language,
  - <html lang>, title, description, og:*, canonical for that language,
  - FAQPage JSON-LD in that language (JobPosting stays on the English pages only),
  - links and asset paths made absolute so they work from /ua/ and /ru/.

Run from the repository root: python3 tools/build_i18n.py
The GitHub deploy workflow runs it before uploading; ua/ and ru/ are not committed.
The script exits with an error if anything looks wrong, so a bad build never deploys.
"""

import html
import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SITE = 'https://hilstrucking.com/'
PAGES = ['index.html', 'owner-operators.html']
# i18n key -> URL folder, <html lang>, og:locale
LANGS = {'uk': ('ua', 'uk', 'uk_UA'), 'ru': ('ru', 'ru', 'ru_RU')}

# Head texts are not in i18nData, so they live here (style rules from CLAUDE.md apply)
META = {
    'index.html': {
        'uk': {
            'title': 'Робота CDL-A: Водії та Owner-Operators | HILS MC-1755680',
            'description': "Company Drivers: $2,500 на тиждень або 32% від гросу. Owner-Operators: диспетч від 6%. Оплата щоп'ятниці. HILS Trucking, MC-1755680, Пенсильванія.",
            'og_title': 'HILS Trucking LLC (MC-1755680): запрошуємо CDL-A Водіїв і Owner-Operators',
            'og_description': 'Гарантія $2,500 на тиждень або 32% від гросу. Оригінальні рейт-кони, відкритий факторинг, без ескроу. Levittown, PA.',
        },
        'ru': {
            'title': 'Работа CDL-A: Водители и Owner-Operators | HILS MC-1755680',
            'description': 'Company Drivers: $2,500 в неделю или 32% от гросса. Owner-Operators: диспетч от 6%. Оплата каждую пятницу. HILS Trucking, MC-1755680, Пенсильвания.',
            'og_title': 'HILS Trucking LLC (MC-1755680): приглашаем CDL-A Водителей и Owner-Operators',
            'og_description': 'Гарантия $2,500 в неделю или 32% от гросса. Оригинальные рейт-коны, открытый факторинг, без эскроу. Levittown, PA.',
        },
    },
    'owner-operators.html': {
        'uk': {
            'title': 'Owner-Operators: диспетч від 6% | HILS MC-1755680',
            'description': 'Smooth Start: диспетч від 6%, оригінальні рейт-кони, відкритий факторинг, уся знижка TA/Petro на пальне. Dry Van, Flatbed, Step Deck. MC-1755680.',
            'og_title': 'HILS Trucking LLC: Owner-Operators, диспетч від 6% (Smooth Start)',
            'og_description': 'Smooth Start: диспетч 6% → 12%, оригінальні рейт-кони, відкритий факторинг, уся знижка TA/Petro на пальне. MC-1755680.',
        },
        'ru': {
            'title': 'Owner-Operators: диспетч от 6% | HILS MC-1755680',
            'description': 'Smooth Start: диспетч от 6%, оригинальные рейт-коны, открытый факторинг, вся скидка TA/Petro на топливо. Dry Van, Flatbed, Step Deck. MC-1755680.',
            'og_title': 'HILS Trucking LLC: Owner-Operators, диспетч от 6% (Smooth Start)',
            'og_description': 'Smooth Start: диспетч 6% → 12%, оригинальные рейт-коны, открытый факторинг, вся скидка TA/Petro на топливо. MC-1755680.',
        },
    },
}

VOID = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'}


class I18nScanner(HTMLParser):
    """Finds the inner range of every [data-i18n] element and every [data-i18n-ph] start tag."""

    def __init__(self, text):
        super().__init__(convert_charrefs=False)
        self.line_starts = [0]
        for m in re.finditer('\n', text):
            self.line_starts.append(m.end())
        self.open = []
        self.ranges = []
        self.placeholders = []
        self.feed(text)
        self.close()

    def _pos(self):
        line, col = self.getpos()
        return self.line_starts[line - 1] + col

    def _start(self, tag, attrs, self_closing):
        start = self._pos()
        end = start + len(self.get_starttag_text())
        a = dict(attrs)
        if 'data-i18n-ph' in a:
            self.placeholders.append((start, end, a['data-i18n-ph']))
        if self_closing or tag in VOID:
            return
        for o in self.open:
            if o['tag'] == tag:
                o['depth'] += 1
        if 'data-i18n' in a:
            self.open.append({'tag': tag, 'depth': 0, 'key': a['data-i18n'], 'inner': end})

    def handle_starttag(self, tag, attrs):
        self._start(tag, attrs, False)

    def handle_startendtag(self, tag, attrs):
        self._start(tag, attrs, True)

    def handle_endtag(self, tag):
        pos = self._pos()
        for o in list(self.open):
            if o['tag'] != tag:
                continue
            if o['depth']:
                o['depth'] -= 1
            else:
                self.ranges.append((o['inner'], pos, o['key']))
                self.open.remove(o)


def plain(text):
    text = re.sub(r'</(p|li|ul)>|<br\s*/?>', ' ', text)
    text = html.unescape(re.sub(r'<[^>]+>', ' ', text))
    text = re.sub(r'\s+', ' ', text).strip()
    return re.sub(r'\s+([.,;:!?])', r'\1', text)


def set_attr(tag_text, name, value):
    value = html.escape(value, quote=True)
    if re.search(rf'\s{name}="[^"]*"', tag_text):
        return re.sub(rf'(\s{name}=")[^"]*(")', lambda m: m.group(1) + value + m.group(2), tag_text, count=1)
    return re.sub(r'\s*/?>$', lambda m: f' {name}="{value}"' + m.group(0), tag_text, count=1)


def replace_once(s, pattern, repl, what):
    s, n = re.subn(pattern, repl, s, count=1)
    if n != 1:
        raise SystemExit(f'build_i18n: could not set {what}')
    return s


def build(page, lang, errors):
    folder, html_lang, locale = LANGS[lang]
    src = (ROOT / page).read_text(encoding='utf-8')
    data = json.loads(re.search(r'const i18nData = (\{.*?\});\n', src, re.S).group(1))[lang]
    out = src

    # 1. Render texts (from the end, so earlier offsets stay valid; skip ranges nested in a replaced one)
    scan = I18nScanner(src)
    if scan.open:
        errors.append(f'{page}: unclosed data-i18n elements: {[o["key"] for o in scan.open]}')
    edits = []
    for start, end, key in scan.ranges:
        if key in data:
            edits.append((start, end, data[key]))
        else:
            errors.append(f'{page} [{lang}]: no text for data-i18n="{key}"')
    for start, end, key in scan.placeholders:
        if key in data:
            edits.append((start, end, set_attr(src[start:end], 'placeholder', data[key])))
    edits.sort(key=lambda e: (e[0], -e[1]))
    kept, last_end = [], -1
    for e in edits:
        if e[0] >= last_end:
            kept.append(e)
            last_end = e[1]
    for start, end, value in reversed(kept):
        out = out[:start] + value + out[end:]

    # 1b. Only this language's texts stay in the page code (the page never switches language in place)
    m_data = re.search(r'(const i18nData = )(\{.*?\})(;\n)', out, re.S)
    if not m_data:
        errors.append(f'{page} [{lang}]: i18nData not found')
    else:
        out = out[:m_data.start(2)] + json.dumps({lang: data}, ensure_ascii=False) + out[m_data.end(2):]

    # 2. Language of the page and head tags
    url = f'{SITE}{folder}/' + ('' if page == 'index.html' else page)
    meta = META[page][lang]
    out = replace_once(out, r'<html lang="en" data-page-lang="en">',
                       f'<html lang="{html_lang}" data-page-lang="{lang}">', '<html lang>')
    out = replace_once(out, r'<title>[^<]*</title>', f'<title>{html.escape(meta["title"], quote=False)}</title>', 'title')
    for attr, key, value in [('name="description"', 'content', meta['description']),
                             ('property="og:title"', 'content', meta['og_title']),
                             ('property="og:description"', 'content', meta['og_description']),
                             ('property="og:url"', 'content', url),
                             ('property="og:locale"', 'content', locale),
                             ('rel="canonical"', 'href', url)]:
        out = replace_once(out, rf'<(?:meta|link) {re.escape(attr)}[^>]*>',
                           lambda m, k=key, v=value: set_attr(m.group(0), k, v), attr)

    # 3. JSON-LD: FAQPage in this language, JobPosting only on the English pages
    faq_ids = re.findall(r'<div class="faq-item[^"]*" id="faq-([^"]+)"', out)

    def fix_ld(m):
        ld = json.loads(m.group(2))
        if ld.get('@type') == 'FAQPage':
            ld['mainEntity'] = [{'@type': 'Question', 'name': plain(data[f'faq_{i}_q']),
                                 'acceptedAnswer': {'@type': 'Answer', 'text': plain(data[f'faq_{i}_a'])}}
                                for i in faq_ids]
            ld['inLanguage'] = html_lang
        elif '@graph' in ld:
            ld['@graph'] = [x for x in ld['@graph'] if x.get('@type') != 'JobPosting']
        elif ld.get('@type') == 'JobPosting':
            return ''
        return m.group(1) + '\n' + json.dumps(ld, ensure_ascii=False, indent=2) + '\n  ' + m.group(3)

    out = re.sub(r'(<script type="application/ld\+json">)(.*?)(</script>)', fix_ld, out, flags=re.S)

    # 4. Paths: the page now lives one folder deeper
    out = re.sub(r'''(["'`(])assets/''', r'\1/assets/', out)
    out = re.sub(r'''(['"])send-mail\.php''', r'\1/send-mail.php', out)
    out = re.sub(r'(href=\\?")privacy\.html', rf'\1/{folder}/privacy.html', out)
    out = re.sub(r'(href=\\?")owner-operators\.html', rf'\1/{folder}/owner-operators.html', out)
    out = re.sub(r'(href=\\?")/#', rf'\1/{folder}/#', out)

    # 5. Checks
    for bad in [r'''["'`(]assets/''', r'''["']send-mail\.php''', r'href=\\?"(privacy|owner-operators)\.html']:
        hits = re.findall(rf'.{{0,30}}{bad}.{{0,20}}', out)
        if hits:
            errors.append(f'{page} [{lang}]: relative path left: {hits[:2]}')
    for m in re.finditer(r'<script type="application/ld\+json">(.*?)</script>', out, re.S):
        try:
            json.loads(m.group(1))
        except ValueError as e:
            errors.append(f'{page} [{lang}]: bad JSON-LD: {e}')
    if lang == 'ru' and re.search('[ёЁ]', ''.join(data.values()) + json.dumps(meta, ensure_ascii=False)):
        errors.append(f'{page} [ru]: letter ё in texts')

    dest = ROOT / folder / page
    dest.parent.mkdir(exist_ok=True)
    dest.write_text(out, encoding='utf-8')
    return dest, len(kept)


PRIVACY_META = {
    'uk': ('Політика конфіденційності | HILS TRUCKING LLC',
           'Як HILS TRUCKING LLC збирає, використовує та захищає персональні дані з hilstrucking.com, форми заявки, дзвінків і повідомлень.'),
    'ru': ('Политика конфиденциальности | HILS TRUCKING LLC',
           'Как HILS TRUCKING LLC собирает, использует и защищает персональные данные с hilstrucking.com, формы заявки, звонков и сообщений.'),
}


def build_privacy(lang, errors):
    """ua/privacy.html and ru/privacy.html: only that language's text, own title/description/canonical."""
    folder, html_lang, _ = LANGS[lang]
    out = (ROOT / 'privacy.html').read_text(encoding='utf-8')
    title, desc = PRIVACY_META[lang]
    url = f'{SITE}{folder}/privacy.html'
    out = replace_once(out, r'<html lang="en" data-page-lang="en">', f'<html lang="{html_lang}" data-page-lang="{lang}">', 'privacy <html>')
    out = replace_once(out, r'<title>[^<]*</title>', f'<title>{html.escape(title, quote=False)}</title>', 'privacy title')
    out = replace_once(out, r'<meta name="description"[^>]*>', lambda m: set_attr(m.group(0), 'content', desc), 'privacy description')
    out = replace_once(out, r'<link rel="canonical"[^>]*>', lambda m: set_attr(m.group(0), 'href', url), 'privacy canonical')
    # keep only this language's article, visible
    for other in ['en', 'uk', 'ru']:
        if other == lang:
            out, n = re.subn(rf'(<article class="doc" lang="{other}" data-lang="{other}") hidden>', r'\1>', out)
        else:
            out, n = re.subn(rf'\s*<article class="doc" lang="{other}" data-lang="{other}"( hidden)?>.*?</article>', '', out, flags=re.S)
            if n != 1:
                errors.append(f'privacy [{lang}]: article {other} not found')
    out = re.sub(r'(["(])assets/', r'\1/assets/', out)
    out = out.replace('href="/"', f'href="/{folder}/"')
    if re.search(r'["(]assets/', out):
        errors.append(f'privacy [{lang}]: relative asset path left')
    if lang == 'ru' and re.search('[ёЁ]', out):
        errors.append('privacy [ru]: letter ё')
    dest = ROOT / folder / 'privacy.html'
    dest.parent.mkdir(exist_ok=True)
    dest.write_text(out, encoding='utf-8')
    return dest


def main():
    errors, built = [], []
    # Browsers only look for <meta charset> in the first 1024 bytes; later and Cyrillic turns into mojibake
    for name in PAGES + ['privacy.html']:
        head = (ROOT / name).read_bytes()[:1024]
        if b'<meta charset="UTF-8"' not in head:
            errors.append(f'{name}: <meta charset="UTF-8" /> must be within the first 1024 bytes')
    for page in PAGES:
        for lang in LANGS:
            dest, n = build(page, lang, errors)
            built.append(f'{dest.relative_to(ROOT)} ({n} texts)')
    for lang in LANGS:
        built.append(str(build_privacy(lang, errors).relative_to(ROOT)))
    print('build_i18n: ' + ', '.join(built))
    if errors:
        print('build_i18n errors:\n  ' + '\n  '.join(errors), file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
