"""
Tighten GekyChat logo masters (trim empty padding) and regenerate all sizes
across desktop, mobile, and web icon folders.
"""
from __future__ import annotations

import io
import os
from pathlib import Path

from PIL import Image

ROOTS = {
    "desktop": Path(r"D:\projects\gekychat_desktop"),
    "mobile": Path(r"D:\projects\gekychat_mobile"),
    "web": Path(r"D:\projects\gekychat"),
}

# Target: logo fills ~86% of canvas (small safe margin).
TARGET_FILL = 0.86


def content_bbox(im: Image.Image, alpha_thresh: int = 10, lum_thresh: int = 40):
    """Bounding box of non-empty logo pixels (ignores near-black / transparent)."""
    rgba = im.convert("RGBA")
    px = rgba.load()
    w, h = rgba.size
    minx, miny, maxx, maxy = w, h, -1, -1
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a > alpha_thresh and (r + g + b) > lum_thresh:
                if x < minx:
                    minx = x
                if y < miny:
                    miny = y
                if x > maxx:
                    maxx = x
                if y > maxy:
                    maxy = y
    if maxx < 0:
        return None
    return (minx, miny, maxx + 1, maxy + 1)


def tighten(im: Image.Image, fill: float = TARGET_FILL, bg=None) -> Image.Image:
    """Crop to content and place on square canvas at `fill` of the side length."""
    rgba = im.convert("RGBA")
    bbox = content_bbox(rgba)
    if not bbox:
        return rgba
    cropped = rgba.crop(bbox)
    cw, ch = cropped.size
    side = max(cw, ch)
    # Scale so the longer side becomes fill * canvas.
    # We'll choose canvas = original size (or at least side).
    canvas_size = max(im.size)
    target = int(round(canvas_size * fill))
    scale = target / side
    nw = max(1, int(round(cw * scale)))
    nh = max(1, int(round(ch * scale)))
    resized = cropped.resize((nw, nh), Image.Resampling.LANCZOS)

    if bg is None:
        out = Image.new("RGBA", (canvas_size, canvas_size), (0, 0, 0, 0))
    else:
        out = Image.new("RGBA", (canvas_size, canvas_size), bg)
    ox = (canvas_size - nw) // 2
    oy = (canvas_size - nh) // 2
    out.paste(resized, (ox, oy), resized)
    return out


def save_png(im: Image.Image, path: Path, size: int | None = None):
    path.parent.mkdir(parents=True, exist_ok=True)
    out = im
    if size is not None:
        out = im.resize((size, size), Image.Resampling.LANCZOS)
    out.save(path, format="PNG", optimize=True)
    print(f"  wrote {path} ({out.size[0]}x{out.size[1]})")


def save_ico(im: Image.Image, path: Path, sizes=(16, 32, 48)):
    path.parent.mkdir(parents=True, exist_ok=True)
    imgs = [im.resize((s, s), Image.Resampling.LANCZOS) for s in sizes]
    imgs[0].save(
        path,
        format="ICO",
        sizes=[(s, s) for s in sizes],
        append_images=imgs[1:],
    )
    print(f"  wrote {path} sizes={sizes}")


def measure(im: Image.Image) -> float:
    bbox = content_bbox(im)
    if not bbox:
        return 0.0
    w, h = im.size
    bw, bh = bbox[2] - bbox[0], bbox[3] - bbox[1]
    return min(bw / w, bh / h) * 100


def main():
    desk = ROOTS["desktop"]
    mob = ROOTS["mobile"]
    web = ROOTS["web"]

    gold_src = desk / "assets/icons/gold_no_text/1024x1024.png"
    white_src = desk / "assets/icons/white_no_text/1024x1024.png"
    if not white_src.exists():
        white_src = desk / "assets/icons/white_no_text/512x512.png"

    gold_raw = Image.open(gold_src)
    white_raw = Image.open(white_src)
    print(f"gold before fill%: {measure(gold_raw):.1f}")
    print(f"white before fill%: {measure(white_raw):.1f}")

    gold = tighten(gold_raw)  # transparent bg
    white = tighten(white_raw)
    gold_black = tighten(gold_raw, bg=(0, 0, 0, 255))  # taskbar / solid

    print(f"gold after fill%: {measure(gold):.1f}")
    print(f"white after fill%: {measure(white):.1f}")
    print(f"gold_black after fill%: {measure(gold_black):.1f}")

    # Keep high-res masters at 1024
    gold_1024 = gold.resize((1024, 1024), Image.Resampling.LANCZOS)
    white_1024 = white.resize((1024, 1024), Image.Resampling.LANCZOS)
    gold_black_1024 = gold_black.resize((1024, 1024), Image.Resampling.LANCZOS)

    desk_gold_sizes = [16, 32, 48, 64, 128, 256, 512, 1024]
    desk_white_sizes = [16, 32, 48, 64, 128, 256, 512, 1024]
    mob_sizes = [48, 72, 96, 144, 192, 512]
    web_theme_sizes = [16, 32, 48, 96]

    print("\n=== Desktop gold_no_text / white_no_text ===")
    for s in desk_gold_sizes:
        save_png(gold_1024, desk / f"assets/icons/gold_no_text/{s}x{s}.png", s)
    for s in desk_white_sizes:
        if (desk / f"assets/icons/white_no_text/{s}x{s}.png").exists() or s in (
            16,
            32,
            48,
            64,
            128,
            256,
            512,
            1024,
        ):
            save_png(white_1024, desk / f"assets/icons/white_no_text/{s}x{s}.png", s)

    print("\n=== Desktop taskbar / app icons ===")
    save_png(gold_black_1024, desk / "assets/icons/app_icon_taskbar.png", 1024)
    # Transparent tight gold also useful as foreground
    save_png(gold_1024, desk / "assets/icons/app_icon_foreground.png", 512)

    print("\n=== Mobile gold_no_text / white_no_text ===")
    for s in mob_sizes:
        save_png(gold_1024, mob / f"assets/icons/gold_no_text/{s}x{s}.png", s)
        save_png(white_1024, mob / f"assets/icons/white_no_text/{s}x{s}.png", s)

    print("\n=== Web theme + favicon PNGs ===")
    for s in web_theme_sizes:
        save_png(gold_1024, web / f"public/icons/theme/gold_no_text/{s}x{s}.png", s)
        save_png(white_1024, web / f"public/icons/theme/white_no_text/{s}x{s}.png", s)
        save_png(gold_1024, web / f"public/icons/{s}x{s}.png", s)

    # Common web aliases
    save_png(gold_1024, web / "public/icons/favicon-16x16.png", 16)
    save_png(gold_1024, web / "public/icons/favicon-32x32.png", 32)
    save_png(gold_1024, web / "public/icons/gekychat-favicon-16.png", 16)
    save_png(gold_1024, web / "public/icons/gekychat-logo-gold-32.png", 32)
    save_png(white_1024, web / "public/icons/gekychat-logo-white-32.png", 32)

    # PWA / UI icons previously left as old green+text assets (~56% fill).
    # Solid brand background so they read large in tabs, login, OG, and install prompts.
    brand_bg = (0, 128, 105, 255)  # #008069
    gold_brand = tighten(gold_raw, bg=brand_bg)
    gold_brand_1024 = gold_brand.resize((1024, 1024), Image.Resampling.LANCZOS)
    print(f"gold_brand after fill%: {measure(gold_brand):.1f}")

    print("\n=== Web PWA / apple-touch icons ===")
    pwa_sizes = [32, 48, 72, 96, 128, 144, 152, 180, 192, 256, 384, 512]
    for s in pwa_sizes:
        save_png(gold_brand_1024, web / f"public/icons/icon-{s}x{s}.png", s)
    save_png(gold_brand_1024, web / "public/icons/apple-touch-icon.png", 180)

    print("\n=== favicon.ico ===")
    save_ico(gold_1024, web / "public/icons/favicon.ico", sizes=(16, 32, 48))
    save_ico(gold_1024, web / "public/favicon.ico", sizes=(16, 32, 48))
    save_ico(gold_1024, web / "public/icons/favicon-16x16.ico", sizes=(16,))

    # Preview for QA
    preview = web / "tools/icon_tighten_preview.png"
    save_png(gold_brand_1024, preview, 256)
    print(f"\nPreview: {preview}")
    print("Done.")


if __name__ == "__main__":
    main()
