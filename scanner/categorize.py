"""
Suggests which kind of photo a picture is, on this server, without any outside service.

Two small local models are used:
  - a face detector (YuNet), because the general classifier below has no "person" class;
  - a general image classifier (EfficientNet-Lite4, 1000 everyday classes), whose classes are
    gathered into the groups a project usually asks for.

The answer is one of: portrait, landscape, indoor, animal, food, still, macro, or None when
nothing is clear enough. It is a suggestion: the contributor and staff can change it.
"""
import os

import numpy as np

CLASSIFIER = "efficientnet-lite4-11.onnx"
FACES = "face_detection_yunet_2023mar.onnx"


def _ids(*parts):
    out = set()
    for p in parts:
        if isinstance(p, tuple):
            out.update(range(p[0], p[1] + 1))
        else:
            out.add(p)
    return out


# Class numbers of the classifier (ImageNet order), gathered by kind of photo.
GROUPS = {
    "animal": _ids((0, 397)),
    "food": _ids((923, 957), (959, 969), 415, 440, 441, 467, 504, 509, 521, 532, 544, 567, 572, 659, 737, 762, 766, 809,
                 828, 868, 898, 907, 909, 910, 987, 998),
    "landscape": _ids(970, 972, 973, 974, 975, 976, 977, 978, 979, 980, 984, 958, 410, 425, 437, 442, 449, 460, 483, 497, 500,
                      525, 536, 538, 562, 580, 634, 649, 660, 663, 668, 682, 698, 703, 706, 716, 718, 727, 743, 755, 807, 821,
                      825, 832, 839, 853, 858, 863, 873, 888, 900, 912, 915, 919, 920, 704, 571, 637, 557, 645, 835, 489,
                      # vehicles and boats, which are nearly always photographed outdoors
                      403, 404, 405, 407, 408, 436, 466, 468, 472, 484, 510, 511, 547, 554, 555, 565, 569, 573, 575, 576, 609,
                      625, 627, 628, 654, 656, 661, 665, 670, 671, 672, 675, 694, 705, 717, 726, 734, 751, 757, 779, 780, 802,
                      803, 814, 817, 820, 829, 833, 847, 864, 866, 867, 871, 874, 895, 913, 914, 595, 856, 730, 621, 561, 517,
                      540, 603, 690, 612, 444, 870, 880, 428, 701, 417, 812),
    "indoor": _ids(406, 423, 424, 431, 435, 453, 454, 492, 493, 495, 498, 516, 520, 526, 527, 534, 548, 553, 556, 559, 564,
                   579, 598, 624, 648, 651, 664, 669, 687, 713, 721, 736, 741, 742, 750, 753, 760, 765, 782, 788, 789, 794,
                   799, 811, 819, 827, 831, 846, 851, 854, 857, 860, 861, 865, 876, 881, 884, 894, 896, 897, 904, 905, 421,
                   480, 582, 800, 877, 886, 620, 729, 882, 545, 859),
    "macro": _ids(985, 986, 988, 989, 990, (991, 997), 599, 815, 490, 488, 506, 616, 885, 911, 640, 971, 783, 677, 772, 455, 644),
    # clothing, uniforms and sports gear: a sign that the picture is of a person even when no face is seen
    "portrait": _ids(399, 400, 411, 416, 422, 433, 439, 445, 451, 452, 457, 461, 465, 474, 501, 515, 518, 524, 543, 552, 560,
                     568, 570, 578, 601, 602, 608, 610, 614, 615, 617, 638, 639, 643, 652, 655, 667, 678, 689, 691, 697, 702,
                     735, 747, 775, 793, 795, 796, 801, 808, 824, 834, 836, 837, 841, 842, 843, 869, 887, 903, 906, 981, 982,
                     983,
                     # balls, rackets and the like: people playing
                     429, 430, 522, 574, 722, 746, 752, 768, 805, 852, 890, 450, 693),
}
_taken = set().union(*GROUPS.values())
GROUPS["still"] = set(range(1000)) - _taken  # everyday objects: instruments, tools, containers, books, clocks...
_INDEX = np.zeros(1000, dtype=np.int64)
ORDER = list(GROUPS)
for _n, _g in enumerate(ORDER):
    _INDEX[list(GROUPS[_g])] = _n

_sessions = {}


def _classifier(models_dir):
    path = os.path.join(models_dir or "", CLASSIFIER)
    if path not in _sessions:
        import onnxruntime as ort
        opt = ort.SessionOptions()
        opt.log_severity_level = 3
        _sessions[path] = ort.InferenceSession(path, sess_options=opt, providers=["CPUExecutionProvider"])
    return _sessions[path]


def _square(rgb, crop):
    """A 224 px square for the classifier: the middle of the picture, or the whole picture squeezed in."""
    import cv2
    h, w = rgb.shape[:2]
    if crop:
        scale = 256.0 / min(h, w)
        img = cv2.resize(rgb, (max(224, round(w * scale)), max(224, round(h * scale))), interpolation=cv2.INTER_AREA)
        h, w = img.shape[:2]
        top, left = (h - 224) // 2, (w - 224) // 2
        img = img[top:top + 224, left:left + 224]
    else:
        img = cv2.resize(rgb, (224, 224), interpolation=cv2.INTER_AREA)
    return (img.astype(np.float32) - 127.0) / 128.0


def _group_scores(rgb, models_dir):
    sess = _classifier(models_dir)
    name = sess.get_inputs()[0].name
    probs = np.zeros(1000, dtype=np.float64)
    for crop in (True, False):
        probs += sess.run(None, {name: _square(rgb, crop)[None]})[0][0]
    probs /= 2.0
    sums = np.bincount(_INDEX, weights=probs, minlength=len(ORDER))
    return {g: float(sums[n]) for n, g in enumerate(ORDER)}


def _faces(rgb, models_dir):
    """Share of the picture covered by the largest face, and how many clear faces there are."""
    import cv2
    path = os.path.join(models_dir or "", FACES)
    h, w = rgb.shape[:2]
    scale = 640.0 / max(h, w)
    small = cv2.resize(rgb, (max(1, round(w * scale)), max(1, round(h * scale))), interpolation=cv2.INTER_AREA)
    sh, sw = small.shape[:2]
    det = cv2.FaceDetectorYN.create(path, "", (sw, sh), 0.8, 0.3, 50)
    _, found = det.detect(cv2.cvtColor(small, cv2.COLOR_RGB2BGR))
    if found is None:
        return 0.0, 0
    shares = [float(f[2] * f[3]) / float(sw * sh) for f in found if f[2] > 0 and f[3] > 0]
    clear = [s for s in shares if s >= 0.002]
    return (max(shares) if shares else 0.0), len(clear)


def _outdoors(rgb):
    """Rough signs of an outdoor scene: blue sky in the upper part, or a lot of green growth."""
    import cv2
    h, w = rgb.shape[:2]
    scale = 160.0 / max(h, w)
    hsv = cv2.cvtColor(cv2.resize(rgb, (max(1, round(w * scale)), max(1, round(h * scale))), interpolation=cv2.INTER_AREA), cv2.COLOR_RGB2HSV)
    hue, sat, val = hsv[..., 0], hsv[..., 1], hsv[..., 2]
    top = slice(0, max(1, int(hsv.shape[0] * 0.4)))
    sky = float(np.mean((hue[top] >= 95) & (hue[top] <= 125) & (sat[top] >= 45) & (val[top] >= 120)))
    green = float(np.mean((hue >= 35) & (hue <= 85) & (sat >= 60) & (val >= 50)))
    return sky, green


def categorize(rgb, models_dir):
    """
    rgb: the picture as a height x width x 3 array, upright.
    Returns {"key": ..., "score": ..., "faces": ..., "groups": {...}}; key is None when unsure.
    Raises when the model files are missing or unreadable (the caller then reports no suggestion).
    """
    groups = _group_scores(rgb, models_dir)
    largest, count = _faces(rgb, models_dir)
    sky, green = _outdoors(rgb)
    if sky >= 0.25 or green >= 0.35:
        groups["landscape"] += 0.25  # the classifier knows few plain views; sky or greenery tips the balance
    key, score = None, 0.0
    best = max(groups, key=groups.get)

    # A clear face decides it, unless the classifier is sure the picture is of an animal or of food
    # (a face on a poster behind a plate, a person far behind a dog).
    face_says = largest >= 0.012 or (count >= 2 and largest >= 0.004)
    strong_other = best in ("animal", "food") and groups[best] >= 0.75 and largest < 0.05
    if face_says and not strong_other:
        key, score = "portrait", min(0.99, 0.6 + largest * 4 + 0.05 * count)
    elif groups[best] >= (0.45 if best == "still" else 0.30):  # "still" gathers every leftover object, so it needs more
        key, score = best, min(0.99, groups[best])
    elif count >= 1 and largest >= 0.004:
        key, score = "portrait", 0.4
    return {"key": key, "score": round(float(score), 3), "faces": count,
            "groups": {g: round(min(v, 1.0), 3) for g, v in groups.items()}}
