"""
Attendance storage — simple CSV-backed store.
File: attendance.csv (columns: Name, Date, Time)
"""

import os
import pandas as pd
from datetime import datetime

BASE_DIR        = os.path.dirname(os.path.abspath(__file__))
ATTENDANCE_FILE = os.path.join(BASE_DIR, "attendance.csv")
_COLUMNS        = ["Name", "Date", "Time"]


# ---------------------------------------------------------------------------
# Low-level helpers
# ---------------------------------------------------------------------------
def _ensure_file():
    if not os.path.exists(ATTENDANCE_FILE):
        pd.DataFrame(columns=_COLUMNS).to_csv(ATTENDANCE_FILE, index=False)


def load_attendance() -> pd.DataFrame:
    """Baca semua rekod sebagai DataFrame."""
    _ensure_file()
    df = pd.read_csv(ATTENDANCE_FILE)
    # pastikan kolum wujud walaupun fail kosong
    for col in _COLUMNS:
        if col not in df.columns:
            df[col] = ""
    return df[_COLUMNS]


def save_attendance(df: pd.DataFrame) -> None:
    """Simpan DataFrame balik ke CSV."""
    df.to_csv(ATTENDANCE_FILE, index=False, columns=_COLUMNS)


# ---------------------------------------------------------------------------
# Public API
# ---------------------------------------------------------------------------
def mark_attendance(name: str) -> bool:
    """Tanda kehadiran sekali sehari. Return True jika baru ditanda."""
    _ensure_file()
    today = datetime.now().strftime("%Y-%m-%d")
    now   = datetime.now().strftime("%H:%M:%S")

    df = load_attendance()
    if not df.empty and ((df["Name"] == name) & (df["Date"] == today)).any():
        return False

    new_row = pd.DataFrame([{"Name": name, "Date": today, "Time": now}])
    df = pd.concat([df, new_row], ignore_index=True)
    save_attendance(df)
    return True


def delete_record(name: str, date: str, time_str: str) -> bool:
    """Buang satu rekod. Return True jika ada yang dibuang."""
    df = load_attendance()
    if df.empty:
        return False

    mask = (df["Name"] == name) & (df["Date"] == date) & (df["Time"] == time_str)
    if not mask.any():
        return False

    df = df[~mask]
    save_attendance(df)
    return True


def delete_all_records() -> None:
    """Kosongkan semua rekod."""
    pd.DataFrame(columns=_COLUMNS).to_csv(ATTENDANCE_FILE, index=False)