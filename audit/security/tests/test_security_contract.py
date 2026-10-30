import json
import math
from pathlib import Path
import unittest

BASE = Path(__file__).resolve().parents[1]
CASES = json.loads((BASE / "fixtures" / "locations.json").read_text())["cases"]

def valid_pair(lat, lon):
    if type(lat) not in (int, float) or type(lon) not in (int, float):
        return False
    return math.isfinite(lat) and math.isfinite(lon) and -90 <= lat <= 90 and -180 <= lon <= 180

def public_feature(page):
    """Reference policy only; production adapter must implement independently."""
    current = page.get("revision")
    return (
        page.get("exists") is True
        and page.get("anonymous_readable") is True
        and page.get("deleted") is False
        and page.get("suppressed") is False
        and page.get("location_approved") is True
        and page.get("approved_revision") == current
        and valid_pair(page.get("latitude"), page.get("longitude"))
        and page.get("safe_public_precision") is True
    )

class SecurityContract(unittest.TestCase):
    def test_coordinate_fixtures(self):
        for case in CASES:
            with self.subTest(case=case["id"]):
                self.assertEqual(valid_pair(case["lat"], case["lon"]), case["valid"])

    def test_fail_closed_matrix(self):
        base = dict(exists=True, anonymous_readable=True, deleted=False,
                    suppressed=False, location_approved=True,
                    approved_revision=3, revision=3, latitude=2, longitude=3,
                    safe_public_precision=True)
        self.assertTrue(public_feature(base))
        for key, value in [
            ("exists", False), ("anonymous_readable", False),
            ("deleted", True), ("suppressed", True),
            ("location_approved", False), ("approved_revision", 2),
            ("revision", 4), ("safe_public_precision", False),
            ("latitude", math.nan), ("longitude", math.inf)
        ]:
            with self.subTest(key=key):
                payload = dict(base, **{key: value})
                self.assertFalse(public_feature(payload))
        for key in base:
            with self.subTest(unknown=key):
                payload = dict(base)
                del payload[key]
                self.assertFalse(public_feature(payload))

    def test_stale_approval_fails_after_edit(self):
        page = dict(exists=True, anonymous_readable=True, deleted=False,
                    suppressed=False, location_approved=True,
                    approved_revision=5, revision=6, latitude=2, longitude=3,
                    safe_public_precision=True)
        self.assertFalse(public_feature(page))

if __name__ == "__main__":
    unittest.main()
