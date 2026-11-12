#!/usr/bin/env python3
"""Marketur App Kit: store pictures made from the customer's icon and the iPhone screenshots.

  python3 scripts/store_art.py customers/<slug> <iphone screenshots dir> <out dir>

Makes, in <out dir>:
  play-icon-512.png             Google Play icon (512 x 512, no transparency)
  play-feature-1024x500.png     Google Play feature graphic (icon and app name on the brand color)
  android/NN-name.png           Google Play phone screenshots (1080 x 2160): the iPhone shots without
                                the iPhone status bar and home bar, so they fit Google's 2:1 limit
Needs Pillow (pip install pillow).
"""
import json, os, sys
from PIL import Image, ImageDraw, ImageFont

cdir, shots, out = sys.argv[1], sys.argv[2], sys.argv[3]
c = json.load(open(os.path.join(cdir, 'customer.json')))
colors = c.get('colors', {})
name = c.get('short_name') or c.get('name')
os.makedirs(os.path.join(out, 'android'), exist_ok=True)

def hex_rgb(h, d=(255, 255, 255)):
    try:
        h = h.lstrip('#'); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
    except Exception:
        return d

paper = hex_rgb(colors.get('paper', '#FFFFFF'))
brand = hex_rgb(c.get('feature_color') or colors.get('active', '#2563EB'))
ink_on_brand = (255, 255, 255) if sum(brand) < 500 else (17, 17, 17)

icon_path = os.path.join(cdir, 'icon.png')
icon = None
if os.path.exists(icon_path):
    icon = Image.open(icon_path).convert('RGBA')
    flat = Image.new('RGB', icon.size, hex_rgb(c.get('icon_background') or colors.get('paper', '#FFFFFF')))
    flat.paste(icon, mask=icon.split()[3])
    flat.resize((512, 512), Image.LANCZOS).save(os.path.join(out, 'play-icon-512.png'))
    print('made play-icon-512.png')

def font(size):
    for f in ['/System/Library/Fonts/Supplemental/Arial Bold.ttf', '/System/Library/Fonts/Helvetica.ttc',
              '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf']:
        if os.path.exists(f):
            return ImageFont.truetype(f, size)
    try:
        return ImageFont.load_default(size)
    except Exception:
        return ImageFont.load_default()

fg = Image.new('RGB', (1024, 500), brand)
d = ImageDraw.Draw(fg)
x = 80
if icon is not None:
    ic = icon.resize((220, 220), Image.LANCZOS)
    mask = Image.new('L', (220, 220), 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, 219, 219), radius=48, fill=255)
    tile = Image.new('RGB', (220, 220), hex_rgb(c.get('icon_background') or colors.get('paper', '#FFFFFF')))
    tile.paste(ic, mask=ic.split()[3])
    fg.paste(tile, (x, 140), mask)
    x += 260
size = 72
while size > 30:
    f = font(size)
    if d.textlength(name, font=f) <= 1024 - x - 60:
        break
    size -= 4
d.text((x, 250), name, font=font(size), fill=ink_on_brand, anchor='lm')
tag = (c.get('store') or {}).get('subtitle', '')
if tag:
    d.text((x, 250 + size), tag, font=font(32), fill=ink_on_brand, anchor='lm')
fg.save(os.path.join(out, 'play-feature-1024x500.png'))
print('made play-feature-1024x500.png')

n = 0
if os.path.isdir(shots):
    for fn in sorted(os.listdir(shots)):
        if not fn.lower().endswith('.png'):
            continue
        im = Image.open(os.path.join(shots, fn)).convert('RGB')
        w, h = im.size
        # 1320 x 2868: drop 186 px of status bar and 42 px of home bar -> 1320 x 2640, exactly 2:1.
        top = round(h * 186 / 2868)
        keep = min(h - top, w * 2)
        im = im.crop((0, top, w, top + keep)).resize((1080, 2160), Image.LANCZOS)
        im.save(os.path.join(out, 'android', fn))
        n += 1
print('made %d Google Play phone screenshots' % n)
