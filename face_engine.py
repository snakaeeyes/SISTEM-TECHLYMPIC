"""
face_engine.py
Handles face detection, dataset creation, model training and recognition
using OpenCV's built-in Haar Cascade detector + LBPH (Local Binary Patterns
Histograms) recognizer.
"""

import os
import json
import cv2
import numpy as np

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CASCADE_PATH = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"

DATASET_DIR = os.path.join(BASE_DIR, "dataset")
TRAINER_DIR = os.path.join(BASE_DIR, "trainer")
TRAINER_FILE = os.path.join(TRAINER_DIR, "trainer.yml")
LABELS_FILE = os.path.join(BASE_DIR, "labels.json")

FACE_SIZE = (200, 200)

os.makedirs(DATASET_DIR, exist_ok=True)
os.makedirs(TRAINER_DIR, exist_ok=True)


class FaceEngine:
    def __init__(self):
        self.detector = cv2.CascadeClassifier(CASCADE_PATH)
        self.recognizer = cv2.face.LBPHFaceRecognizer_create()
        self.labels = self._load_labels()
        self.model_loaded = False
        if os.path.exists(TRAINER_FILE):
            try:
                self.recognizer.read(TRAINER_FILE)
                self.model_loaded = True
            except Exception:
                self.model_loaded = False

    # ---------- labels ----------
    def _load_labels(self):
        if os.path.exists(LABELS_FILE):
            with open(LABELS_FILE, "r") as f:
                raw = json.load(f)
            return {int(k): v for k, v in raw.items()}
        return {}

    def _save_labels(self):
        with open(LABELS_FILE, "w") as f:
            json.dump(self.labels, f)

    # ---------- dataset helpers ----------
    def registered_people(self):
        if not os.path.exists(DATASET_DIR):
            return []
        return sorted(
            [d for d in os.listdir(DATASET_DIR) if os.path.isdir(os.path.join(DATASET_DIR, d))]
        )

    def person_dir(self, name):
        d = os.path.join(DATASET_DIR, name)
        os.makedirs(d, exist_ok=True)
        return d

    def existing_sample_count(self, name):
        d = self.person_dir(name)
        return len([f for f in os.listdir(d) if f.lower().endswith(".jpg")])

    def save_face_sample(self, name, gray_face_img, index):
        d = self.person_dir(name)
        path = os.path.join(d, f"{name}_{index}.jpg")
        cv2.imwrite(path, gray_face_img)

    # ---------- detection ----------
    def detect_faces(self, gray_frame):
        return self.detector.detectMultiScale(
            gray_frame, scaleFactor=1.2, minNeighbors=5, minSize=(90, 90)
        )

    @staticmethod
    def largest_face(faces):
        if len(faces) == 0:
            return None
        return max(faces, key=lambda f: f[2] * f[3])

    def crop_and_normalize(self, gray_frame, box):
        x, y, w, h = box
        face = gray_frame[y : y + h, x : x + w]
        face = cv2.resize(face, FACE_SIZE)
        face = cv2.equalizeHist(face)
        return face

    # ---------- training ----------
    def train(self):
        faces, ids = [], []
        label_map = {}
        current_id = 0

        for person_name in self.registered_people():
            person_path = os.path.join(DATASET_DIR, person_name)
            samples = [f for f in os.listdir(person_path) if f.lower().endswith(".jpg")]
            if not samples:
                continue
            label_map[current_id] = person_name
            for img_name in samples:
                img = cv2.imread(os.path.join(person_path, img_name), cv2.IMREAD_GRAYSCALE)
                if img is None:
                    continue
                faces.append(img)
                ids.append(current_id)
            current_id += 1

        if not faces:
            return False, "No training data found. Please register at least one face first."

        self.recognizer.train(faces, np.array(ids))
        self.recognizer.save(TRAINER_FILE)
        self.labels = label_map
        self._save_labels()
        self.model_loaded = True
        return True, f"Model trained on {len(faces)} images across {len(label_map)} person(s)."

    # ---------- prediction ----------
    def predict(self, gray_face_img):
        """Returns (name, confidence). Lower confidence = better match (LBPH distance)."""
        if not self.model_loaded:
            return None, None
        label_id, confidence = self.recognizer.predict(gray_face_img)
        name = self.labels.get(label_id, "Unknown")
        return name, confidence