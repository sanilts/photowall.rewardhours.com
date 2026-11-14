#!/usr/bin/env python3
"""Photowall in-house photo scanner. Runs fully on this server: no photo is sent anywhere.

Reads one photo (HEIC or JPEG), never changes it, and prints one line of JSON:
  ok, width, height      whether the file opens, and its real size
  sharpness              0 to 100, higher is sharper (catches focus blur and motion blur)
  brightness             average brightness, 0 (black) to 255 (white)
  dhash                  64-bit picture fingerprint used to find duplicates
  safety                 scores from the local safety models (nudity and sexual content)
It also writes a web preview and a thumbnail (JPEG, metadata removed) when asked.
"""
import argparse
import json
import os
import sys
import time

ANALYSIS_SIDE = 2048      # long side used for the sharpness and brightness measures
EXPLICIT = ("FEMALE_GENITALIA_EXPOSED", "MALE_GENITALIA_EXPOSED", "ANUS_EXPOSED")
PARTIAL = ("FEMALE_BREAST_EXPOSED", "BUTTOCKS_EXPOSED")


def fail(code, detail=""):
    print(json.dumps({"ok": False, "error": code, "detail": str(detail)[:300]}))
    sys.exit(0)


def check_container(path):
    """HEIC files are a list of boxes, each saying how long it is. If a box claims more bytes
    than the file has, the upload was cut short even if part of the picture still opens."""
    import struct
    size = os.path.getsize(path)
    with open(path, "rb") as fh:
        head = fh.read(12)
        if len(head) < 12 or head[4:8] != b"ftyp":
            return  # not a HEIC style file (JPEG is checked when it is decoded)
        pos = 0
        while pos < size:
            fh.seek(pos)
            hdr = fh.read(16)
            if len(hdr) < 8:
                raise ValueError("file ends in the middle of a box header")
            length = struct.unpack(">I", hdr[:4])[0]
            if length == 1:
                if len(hdr) < 16:
                    raise ValueError("file ends in the middle of a box header")
                length = struct.unpack(">Q", hdr[8:16])[0]
            elif length == 0:
                length = size - pos
            if length < 8 or pos + length > size:
                raise ValueError("file is shorter than its contents say (incomplete upload)")
            pos += length


def load_rgb(path):
    """Open the picture upright, in 8-bit RGB. Raises if the file is broken or incomplete."""
    from PIL import Image, ImageOps
    import pillow_heif
    pillow_heif.register_heif_opener()
    Image.MAX_IMAGE_PIXELS = 250_000_000
    check_container(path)
    img = Image.open(path)
    img.load()
    img = ImageOps.exif_transpose(img)
    if img.mode != "RGB":
        img = img.convert("RGB")
    return img


def sharpness(gray):
    """Sharpness from 0 to 100 (higher is sharper), measured the "re-blur" way: a sharp picture
    loses a lot of edge detail when it is blurred again, a blurry one loses almost none.
    It is checked across and down, and the worse direction counts, so motion blur is caught too.
    The result does not depend on how bright the picture is."""
    import cv2
    import numpy as np
    g = gray.astype(np.float32) / 255.0
    worst = 0.0
    for size, dx, dy in (((11, 1), 1, 0), ((1, 11), 0, 1)):
        edges = np.abs(cv2.Sobel(g, cv2.CV_32F, dx, dy, ksize=3))
        again = np.abs(cv2.Sobel(cv2.blur(g, size), cv2.CV_32F, dx, dy, ksize=3))
        total = float(edges.sum())
        kept = float(np.maximum(0, edges - again).sum())
        worst = max(worst, abs(total - kept) / (total + 1e-9))
    return (1.0 - worst) * 100.0


def dhash(gray):
    import cv2
    small = cv2.resize(gray, (9, 8), interpolation=cv2.INTER_AREA)
    bits = 0
    for y in range(8):
        for x in range(8):
            bits = (bits << 1) | (1 if small[y, x] > small[y, x + 1] else 0)
    return "%016x" % bits


def safety_scan(bgr, models_dir):
    """Two local models: an overall score (OpenNSFW) and a body-part detector (NudeNet)."""
    import cv2
    out = {"engines": [], "score": None, "parts": []}
    proto = os.path.join(models_dir or "", "deploy.prototxt")
    weights = os.path.join(models_dir or "", "resnet_50_1by2_nsfw.caffemodel")
    if os.path.isfile(proto) and os.path.isfile(weights):
        try:
            net = cv2.dnn.readNetFromCaffe(proto, weights)
            img = cv2.resize(bgr, (256, 256), interpolation=cv2.INTER_AREA)[16:240, 16:240]
            net.setInput(cv2.dnn.blobFromImage(img, 1.0, (224, 224), (104, 117, 123), swapRB=False, crop=False))
            out["score"] = round(float(net.forward().flatten()[1]), 4)
            out["engines"].append("opennsfw")
        except Exception as ex:  # keep going with the other model
            out["opennsfw_error"] = str(ex)[:200]
    try:
        from nudenet import NudeDetector
        for d in NudeDetector().detect(bgr):
            if d["class"] in EXPLICIT or d["class"] in PARTIAL:
                out["parts"].append({"label": d["class"], "score": round(float(d["score"]), 3)})
        out["engines"].append("nudenet")
    except Exception as ex:
        out["nudenet_error"] = str(ex)[:200]
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in", dest="src", required=True)
    ap.add_argument("--preview")
    ap.add_argument("--thumb")
    ap.add_argument("--models", default="")
    ap.add_argument("--no-safety", action="store_true")
    a = ap.parse_args()
    t0 = time.time()

    try:
        img = load_rgb(a.src)
    except Exception as ex:
        fail("broken", ex)

    import cv2
    import numpy as np
    from PIL import Image

    width, height = img.size
    if width < 16 or height < 16:
        fail("broken", "image too small")

    work = img.copy()
    work.thumbnail((ANALYSIS_SIDE, ANALYSIS_SIDE), Image.LANCZOS)
    rgb = np.asarray(work)
    gray = cv2.cvtColor(rgb, cv2.COLOR_RGB2GRAY)
    result = {
        "ok": True, "width": width, "height": height,
        "sharpness": round(sharpness(gray), 2),
        "brightness": round(float(gray.mean()), 2),
        "dhash": dhash(gray),
    }
    if not a.no_safety:
        result["safety"] = safety_scan(cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR), a.models)

    for path, side, quality in ((a.preview, 1600, 82), (a.thumb, 480, 78)):
        if path:
            out = img.copy()
            out.thumbnail((side, side), Image.LANCZOS)
            out.save(path, "JPEG", quality=quality, optimize=True)

    result["ms"] = int((time.time() - t0) * 1000)
    print(json.dumps(result))


if __name__ == "__main__":
    main()
