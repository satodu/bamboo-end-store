import os
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

def make_neon_pixel_icon(draw_func, filename):
    canvas_size = 64
    base = Image.new('RGBA', (canvas_size, canvas_size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(base)
    
    # Run custom drawing function on 64x64 grid
    draw_func(draw)
    
    # Scale up with nearest neighbor to 1024x1024 (16x pixel zoom)
    pixel_art = base.resize((1024, 1024), Image.NEAREST)
    
    # Multi-layer glow effect
    glow_wide = pixel_art.filter(ImageFilter.GaussianBlur(radius=32))
    glow_mid = pixel_art.filter(ImageFilter.GaussianBlur(radius=14))
    glow_tight = pixel_art.filter(ImageFilter.GaussianBlur(radius=6))
    
    # Composite transparent canvas with glows and crisp pixel art
    transparent = Image.new('RGBA', (1024, 1024), (0, 0, 0, 0))
    final = Image.alpha_composite(transparent, glow_wide)
    final = Image.alpha_composite(final, glow_mid)
    final = Image.alpha_composite(final, glow_tight)
    final = Image.alpha_composite(final, pixel_art)
    
    png_path = os.path.join('public/images/categories', f"{filename}.png")
    final.save(png_path, 'PNG')
    print(f"Created {png_path}")

# Palette definitions
PURPLE = (153, 58, 241, 255)       # Neon violet EndeavourOS
CYAN = (55, 215, 255, 255)         # Electric cyan
MAGENTA = (215, 25, 225, 255)      # Cyber magenta
WHITE = (255, 255, 255, 255)       # Core white
DARK_PURPLE = (40, 18, 75, 220)    # Soft fill

def draw_personal_security(d):
    # Cyber Shield
    shield = [(32, 6), (50, 12), (50, 38), (42, 50), (32, 58), (22, 50), (14, 38), (14, 12), (32, 6)]
    d.polygon(shield, fill=DARK_PURPLE, outline=PURPLE, width=2)
    
    # Inner glowing contour
    inner_shield = [(32, 11), (45, 16), (45, 36), (39, 45), (32, 52), (25, 45), (19, 36), (19, 16), (32, 11)]
    d.polygon(inner_shield, outline=CYAN, width=1)
    
    # Shackle of padlock
    d.arc([26, 22, 38, 34], start=180, end=0, fill=CYAN, width=3)
    # Body of padlock
    d.rectangle([25, 29, 39, 43], fill=MAGENTA, outline=PURPLE, width=2)
    # Keyhole
    d.rectangle([30, 33, 34, 37], fill=WHITE)
    d.line([(32, 37), (32, 40)], fill=WHITE, width=2)
    
    # Sparkles
    d.point((10, 16), fill=CYAN)
    d.point((54, 18), fill=MAGENTA)
    d.point((48, 48), fill=CYAN)

def draw_torrent_clients(d):
    # Magnetic wave arcs at top
    d.arc([16, 8, 48, 30], start=210, end=330, fill=PURPLE, width=2)
    d.arc([22, 12, 42, 28], start=210, end=330, fill=CYAN, width=2)
    
    # Center big download arrow
    # Stem
    d.rectangle([28, 16, 36, 34], fill=MAGENTA, outline=PURPLE, width=2)
    d.line([(32, 18), (32, 32)], fill=WHITE, width=2)
    
    # Arrow head
    arrow_head = [(20, 34), (32, 48), (44, 34), (36, 34), (28, 34)]
    d.polygon(arrow_head, fill=CYAN, outline=PURPLE, width=2)
    d.polygon([(26, 35), (32, 43), (38, 35)], fill=WHITE)
    
    # Peer transfer tray / receiving dock
    d.line([(14, 46), (14, 54), (50, 54), (50, 46)], fill=PURPLE, width=3)
    d.line([(16, 48), (16, 52), (48, 52), (48, 48)], fill=CYAN, width=1)
    
    # Transfer packets / data bits
    d.rectangle([10, 26, 12, 28], fill=CYAN)
    d.rectangle([52, 28, 54, 30], fill=MAGENTA)
    d.rectangle([31, 56, 33, 58], fill=MAGENTA)

def draw_text_editors(d):
    # Terminal / Editor Window
    # Frame
    d.rectangle([10, 10, 54, 54], fill=DARK_PURPLE, outline=PURPLE, width=2)
    
    # Title bar
    d.rectangle([10, 10, 54, 18], fill=(60, 25, 110, 255), outline=PURPLE, width=1)
    # Window controls (three dots)
    d.rectangle([14, 13, 16, 15], fill=MAGENTA)
    d.rectangle([19, 13, 21, 15], fill=CYAN)
    d.rectangle([24, 13, 26, 15], fill=PURPLE)
    
    # Code lines
    # Line 1: Keyword + function
    d.line([(16, 24), (24, 24)], fill=MAGENTA, width=2)
    d.line([(27, 24), (42, 24)], fill=CYAN, width=2)
    
    # Line 2: Indented code
    d.line([(22, 31), (34, 31)], fill=PURPLE, width=2)
    d.line([(37, 31), (46, 31)], fill=WHITE, width=2)
    
    # Line 3: Indented code
    d.line([(22, 38), (38, 38)], fill=CYAN, width=2)
    
    # Line 4: Blinking cursor
    d.line([(16, 45), (28, 45)], fill=MAGENTA, width=2)
    d.rectangle([31, 44, 33, 47], fill=WHITE) # Block cursor!
    
    # Decorative sparkle
    d.point((56, 8), fill=CYAN)
    d.point((7, 48), fill=PURPLE)

def draw_office(d):
    # Digital Briefcase / Document Sheet
    # Document Page
    d.rectangle([14, 10, 50, 54], fill=DARK_PURPLE, outline=PURPLE, width=2)
    # Folded top-right corner
    d.polygon([(40, 10), (50, 20), (40, 20)], fill=(80, 35, 140, 255), outline=CYAN, width=1)
    
    # Header bar
    d.rectangle([18, 16, 36, 19], fill=CYAN)
    
    # Spreadsheet / Data chart grid
    d.rectangle([18, 25, 46, 47], outline=PURPLE, width=1)
    d.line([(18, 32), (46, 32)], fill=PURPLE, width=1)
    d.line([(18, 39), (46, 39)], fill=PURPLE, width=1)
    d.line([(27, 25), (27, 47)], fill=PURPLE, width=1)
    d.line([(36, 25), (36, 47)], fill=PURPLE, width=1)
    
    # Highlight cells
    d.rectangle([19, 26, 26, 31], fill=MAGENTA)
    d.rectangle([28, 33, 35, 38], fill=CYAN)
    d.rectangle([37, 40, 45, 46], fill=WHITE)
    
    # Stylus / Pen across
    pen = [(46, 40), (54, 48), (52, 50), (44, 42)]
    d.polygon(pen, fill=CYAN, outline=PURPLE, width=1)
    d.point((43, 41), fill=WHITE)

def draw_pdf_viewers(d):
    # Folio / Document
    d.rectangle([15, 8, 49, 56], fill=DARK_PURPLE, outline=PURPLE, width=2)
    # Top-right folded corner
    d.polygon([(37, 8), (49, 20), (37, 20)], fill=(90, 40, 160, 255), outline=MAGENTA, width=1)
    
    # Glowing Bookmark Ribbon hanging down
    ribbon = [(22, 6), (30, 6), (30, 26), (26, 22), (22, 26)]
    d.polygon(ribbon, fill=MAGENTA, outline=PURPLE, width=1)
    
    # Document text lines
    d.line([(22, 32), (42, 32)], fill=CYAN, width=2)
    d.line([(22, 38), (42, 38)], fill=PURPLE, width=2)
    d.line([(22, 44), (36, 44)], fill=CYAN, width=2)
    d.line([(22, 50), (40, 50)], fill=WHITE, width=2)
    
    # Magnifier / eye inspect badge
    d.ellipse([34, 38, 46, 50], fill=DARK_PURPLE, outline=CYAN, width=2)
    d.line([(44, 48), (48, 53)], fill=MAGENTA, width=2)
    d.point((38, 42), fill=WHITE)

def draw_personal_finance(d):
    # Cyber Coin
    d.ellipse([14, 14, 46, 46], fill=DARK_PURPLE, outline=PURPLE, width=2)
    d.ellipse([18, 18, 42, 42], outline=CYAN, width=1)
    
    # Diamond / Currency symbol in center
    diamond = [(30, 22), (37, 30), (30, 38), (23, 30)]
    d.polygon(diamond, fill=MAGENTA, outline=PURPLE, width=1)
    d.polygon([(27, 27), (33, 27), (30, 33)], fill=WHITE)
    
    # Ascending Stock / Growth Chart bars
    d.rectangle([44, 36, 48, 52], fill=PURPLE, outline=PURPLE)
    d.rectangle([49, 28, 53, 52], fill=MAGENTA, outline=MAGENTA)
    d.rectangle([54, 18, 58, 52], fill=CYAN, outline=CYAN)
    
    # Trend arrow going up
    d.line([(38, 38), (56, 16)], fill=WHITE, width=2)
    d.polygon([(56, 12), (60, 16), (54, 18)], fill=WHITE)
    
    # Sparkles
    d.point((12, 12), fill=CYAN)
    d.point((20, 50), fill=MAGENTA)

if __name__ == '__main__':
    make_neon_pixel_icon(draw_personal_security, 'personal_security')
    make_neon_pixel_icon(draw_torrent_clients, 'torrent_clients')
    make_neon_pixel_icon(draw_text_editors, 'text_editors')
    make_neon_pixel_icon(draw_office, 'office')
    make_neon_pixel_icon(draw_pdf_viewers, 'pdf_viewers')
    make_neon_pixel_icon(draw_personal_finance, 'personal_finance')
    print("All 6 remaining procedural pixel art icons generated!")
