"""
Face Recognition Attendance Register — Flask App
==================================================
Sistem Kehadiran AI dengan kawalan peranan (Admin & User).
Disambungkan ke pangkalan data MySQL (XAMPP).
"""

import os
import shutil
import threading
import time
import functools

import cv2
from flask import (
    Flask, Response, render_template, request,
    redirect, url_for, jsonify, send_file, flash
)
from flask_login import LoginManager, login_user, logout_user, login_required, current_user

import face_engine
from face_engine import FaceEngine
from attendance_store import (
    mark_attendance, load_attendance, save_attendance,
    delete_record, delete_all_records, ATTENDANCE_FILE,
)
from models import db, User

app = Flask(__name__)
app.secret_key = "change-this-secret-key-in-production"

# ==========================================
# KONFIGURASI DATABASE MYSQL (XAMPP)
# ==========================================
# Pastikan anda telah mencipta database bernama 'face_attendance' di phpMyAdmin
app.config['SQLALCHEMY_DATABASE_URI'] = 'mysql+pymysql://root:@localhost/face_attendance'
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
# ------------------------------------------

db.init_app(app)
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'login'
login_manager.login_message = "Sila login terlebih dahulu."
login_manager.login_message_category = "warning"

@login_manager.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))

# Decorator khas untuk Admin sahaja
def admin_required(f):
    @functools.wraps(f)
    def decorated_function(*args, **kwargs):
        if not current_user.is_authenticated or current_user.role != 'admin':
            flash("Akses Ditolak. Anda bukan Admin.", "danger")
            # Jika user biasa cuba akses, hantar ke halaman mark attendance user
            return redirect(url_for('user_recognize'))
        return f(*args, **kwargs)
    return decorated_function

engine = FaceEngine()
camera_lock = threading.Lock()
register_state = {"active": False, "name": None, "captured": 0, "target": 30, "done": False}
recognize_state = {"active": False, "recent": []}

def get_camera(index=0):
    cam = cv2.VideoCapture(index)
    if not cam.isOpened():
        time.sleep(0.5)
        cam = cv2.VideoCapture(index)
    return cam

# ==========================================
# ROUTES: AUTH (LOGIN, REGISTER, LOGOUT)
# ==========================================
@app.route("/login", methods=["GET", "POST"])
def login():
    if current_user.is_authenticated:
        if current_user.role == 'admin': return redirect(url_for('index'))
        return redirect(url_for('user_recognize'))
        
    if request.method == "POST":
        username = request.form.get("username")
        password = request.form.get("password")
        selected_role = request.form.get("role") # Ambil nilai dari dropdown
        
        user = User.query.filter_by(username=username).first()
        
        if user and user.check_password(password):
            # Semak sama ada peranan yang dipilih sepadan dengan pangkalan data
            if user.role != selected_role:
                flash(f"Akaun ini didaftarkan sebagai {user.role.upper()}. Sila pilih peranan yang betul.", "danger")
                return redirect(url_for('login'))
                
            login_user(user)
            flash(f"Selamat kembali, {user.username}!", "success")
            
            if user.role == 'admin':
                return redirect(url_for('index'))
            return redirect(url_for('user_recognize'))
            
        flash("Username atau password salah.", "danger")
        
    return render_template("login.html")

@app.route("/register_admin", methods=["GET", "POST"])
def register_admin():
    # Jika sudah ada Admin di dalam sistem, hanya Admin yang login boleh tambah akaun baru
    if User.query.filter_by(role='admin').count() > 0 and (not current_user.is_authenticated or current_user.role != 'admin'):
        flash("Admin sudah wujud. Sila login sebagai Admin untuk menambah akaun baru.", "warning")
        return redirect(url_for('login'))

    if request.method == "POST":
        username = request.form.get("username")
        password = request.form.get("password")
        selected_role = request.form.get("role", "user") # Ambil role dari dropdown

        # Semak jika username sudah wujud
        if User.query.filter_by(username=username).first():
            flash("Username sudah wujud.", "danger")
            return redirect(url_for('register_admin'))
            
        # Cipta user baru mengikut role yang dipilih
        new_user = User(username=username, role=selected_role)
        new_user.set_password(password)
        
        db.session.add(new_user)
        db.session.commit()
        
        flash(f"Akaun {selected_role.upper()} berjaya didaftarkan. Sila login.", "success")
        
        # Jika yang mendaftar adalah Admin, kekalkan di page register
        if current_user.is_authenticated and current_user.role == 'admin':
            return redirect(url_for('register_user'))
            
        return redirect(url_for('login'))
        
    return render_template("register_admin.html")

@app.route("/register_user", methods=["GET", "POST"])
@login_required
@admin_required
def register_user():
    if request.method == "POST":
        username = request.form.get("username")
        password = request.form.get("password")
        selected_role = request.form.get("role", "user")

        if User.query.filter_by(username=username).first():
            flash("Username sudah wujud. Sila guna nama lain.", "danger")
            return redirect(url_for('register_user'))
            
        new_user = User(username=username, role=selected_role)
        new_user.set_password(password)
        
        db.session.add(new_user)
        db.session.commit()
        
        flash(f"Akaun {selected_role.upper()} untuk '{username}' berjaya didaftarkan!", "success")
        return redirect(url_for('register_user'))
        
    return render_template("register_user.html")

@app.route("/logout")
@login_required
def logout():
    logout_user()
    return redirect(url_for('login'))

# ==========================================
# ROUTES: ADMIN (Dilindungi)
# ==========================================
@app.route("/")
@login_required
@admin_required
def index():
    return render_template("index.html", people=engine.registered_people(), model_loaded=engine.model_loaded)

@app.route("/register", methods=["GET", "POST"])
@login_required
@admin_required
def register():
    if request.method == "POST":
        name = request.form.get("name", "").strip().replace(" ", "_")
        num_samples = int(request.form.get("num_samples", 30))
        if not name:
            flash("Please enter a name.", "danger")
            return redirect(url_for("register"))
        existing = engine.existing_sample_count(name)
        register_state.update({"active": True, "name": name, "captured": existing, "target": existing + num_samples, "done": False})
        return redirect(url_for("register", started=1, name=name))
    return render_template("register.html", people=engine.registered_people(), started=request.args.get("started"), name=request.args.get("name", ""))

@app.route("/register/video_feed")
@login_required
@admin_required
def register_video_feed():
    return Response(gen_register_frames(), mimetype="multipart/x-mixed-replace; boundary=frame")

def gen_register_frames():
    with camera_lock:
        cam = get_camera()
        try:
            frame_skip = 0
            while register_state["active"] and not register_state["done"]:
                ok, frame = cam.read()
                if not ok: break
                frame = cv2.flip(frame, 1)
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                faces = engine.detect_faces(gray)
                box = engine.largest_face(faces)
                if box is not None:
                    x, y, w, h = box
                    cv2.rectangle(frame, (x, y), (x + w, y + h), (0, 200, 0), 2)
                    frame_skip += 1
                    if frame_skip % 3 == 0 and register_state["captured"] < register_state["target"]:
                        face_img = engine.crop_and_normalize(gray, box)
                        engine.save_face_sample(register_state["name"], face_img, register_state["captured"])
                        register_state["captured"] += 1
                else:
                    cv2.putText(frame, "No face detected", (20, 40), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 0, 255), 2)
                cv2.putText(frame, f"Captured: {register_state['captured']}/{register_state['target']}", (20, 460), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 0), 2)
                if register_state["captured"] >= register_state["target"]:
                    register_state["done"] = True
                    register_state["active"] = False
                ok2, buf = cv2.imencode(".jpg", frame)
                if not ok2: continue
                yield (b"--frame\r\nContent-Type: image/jpeg\r\n\r\n" + buf.tobytes() + b"\r\n")
        finally:
            cam.release()

@app.route("/register/progress")
@login_required
@admin_required
def register_progress(): return jsonify(register_state)

@app.route("/register/stop", methods=["POST"])
@login_required
@admin_required
def register_stop():
    register_state["active"] = False
    return jsonify({"ok": True})

@app.route("/register/train", methods=["POST"])
@login_required
@admin_required
def register_train():
    ok, msg = engine.train()
    flash(msg, "success" if ok else "danger")
    return redirect(url_for("register"))

@app.route("/register/delete_person", methods=["POST"])
@login_required
@admin_required
def register_delete_person():
    data = request.get_json(silent=True) or {}
    name = (data.get("name") or "").strip()
    if not name: return jsonify({"ok": False, "msg": "Nama kosong."}), 400
    safe_name = os.path.basename(name)
    person_dir = os.path.join(face_engine.DATASET_DIR, safe_name)
    if not os.path.isdir(person_dir): return jsonify({"ok": False, "msg": f"'{name}' tidak dijumpai."}), 404
    try:
        shutil.rmtree(person_dir)
        remaining_people = engine.registered_people()
        if len(remaining_people) == 0:
            if os.path.exists(face_engine.TRAINER_FILE): os.remove(face_engine.TRAINER_FILE)
            if os.path.exists(face_engine.LABELS_FILE): os.remove(face_engine.LABELS_FILE)
            engine.model_loaded = False
        else:
            engine.train()
    except Exception as e:
        return jsonify({"ok": False, "msg": f"Gagal buang: {e}"}), 500
    return jsonify({"ok": True, "msg": f"'{name}' berjaya dibuang."})

@app.route("/recognize")
@login_required
@admin_required
def recognize():
    if not engine.model_loaded:
        flash("No trained model yet. Register at least one person and train the model first.", "warning")
    recognize_state["active"] = True
    recognize_state["recent"] = []
    return render_template("recognize.html", model_loaded=engine.model_loaded)

@app.route("/attendance")
@login_required
@admin_required
def attendance():
    df = load_attendance()
    dates = sorted(df["Date"].unique(), reverse=True) if not df.empty else []
    selected_date = request.args.get("date", "All")
    view_df = df if (selected_date == "All" or df.empty) else df[df["Date"] == selected_date]
    if not view_df.empty:
        view_df = view_df.sort_values(["Date", "Time"], ascending=False)
    records = view_df.to_dict(orient="records")
    return render_template("attendance.html", records=records, dates=dates, selected_date=selected_date)

# ==========================================
# ROUTES: URUS PENGGUNA (ADMIN)
# ==========================================
@app.route("/manage_users")
@login_required
@admin_required
def manage_users():
    users = User.query.all()
    return render_template("manage_users.html", users=users)

@app.route("/edit_user", methods=["POST"])
@login_required
@admin_required
def edit_user():
    data = request.get_json(silent=True) or {}
    user_id = data.get("id")
    new_username = data.get("username")
    new_password = data.get("password")
    new_role = data.get("role")

    user = User.query.get(user_id)
    if not user:
        return jsonify({"ok": False, "msg": "Pengguna tidak dijumpai."}), 404

    # Semak jika username baru sudah digunakan oleh orang lain
    if new_username != user.username:
        existing = User.query.filter_by(username=new_username).first()
        if existing:
            return jsonify({"ok": False, "msg": "Username sudah digunakan oleh pengguna lain."}), 400

    # Kemas kini data
    user.username = new_username
    user.role = new_role
    
    # Jika password diisi, kemas kini password
    if new_password and len(new_password) > 0:
        user.set_password(new_password)

    db.session.commit()
    return jsonify({"ok": True, "msg": f"Akaun '{user.username}' berjaya dikemas kini."})

@app.route("/delete_user", methods=["POST"])
@login_required
@admin_required
def delete_user():
    data = request.get_json(silent=True) or {}
    user_id = data.get("id")
    
    # Pastikan admin tak boleh padam diri sendiri
    if int(user_id) == current_user.id:
        return jsonify({"ok": False, "msg": "Anda tidak boleh memadam akaun anda sendiri."}), 400
        
    user = User.query.get(user_id)
    if not user:
        return jsonify({"ok": False, "msg": "Pengguna tidak dijumpai."}), 404
        
    db.session.delete(user)
    db.session.commit()
    
    return jsonify({"ok": True, "msg": f"Akaun '{user.username}' berjaya dibuang."})

# ==========================================
# ROUTES: USER (Mark & View Only) - FOLDER /user/
# ==========================================
@app.route("/user/recognize")
@login_required
def user_recognize():
    if not engine.model_loaded:
        flash("No trained model yet. Sila hubungi Admin.", "warning")
    recognize_state["active"] = True
    recognize_state["recent"] = []
    return render_template("user/user_recognize.html", model_loaded=engine.model_loaded)

@app.route("/user/attendance")
@login_required
def user_attendance():
    df = load_attendance()
    dates = sorted(df["Date"].unique(), reverse=True) if not df.empty else []
    selected_date = request.args.get("date", "All")
    view_df = df if (selected_date == "All" or df.empty) else df[df["Date"] == selected_date]
    if not view_df.empty:
        view_df = view_df.sort_values(["Date", "Time"], ascending=False)
    records = view_df.to_dict(orient="records")
    return render_template("user/user_attendance.html", records=records, dates=dates, selected_date=selected_date)

# ==========================================
# ROUTES: SHARED (Feed & Actions)
# ==========================================
@app.route("/recognize/video_feed")
@login_required
def recognize_video_feed():
    threshold = int(request.args.get("threshold", 70))
    return Response(gen_recognize_frames(threshold), mimetype="multipart/x-mixed-replace; boundary=frame")

def gen_recognize_frames(threshold=70):
    with camera_lock:
        cam = get_camera()
        recognize_state["active"] = True
        try:
            while recognize_state["active"]:
                ok, frame = cam.read()
                if not ok: break
                frame = cv2.flip(frame, 1)
                gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
                faces = engine.detect_faces(gray)
                for (x, y, w, h) in faces:
                    face_img = engine.crop_and_normalize(gray, (x, y, w, h))
                    name, confidence = engine.predict(face_img)
                    if name is not None and confidence is not None and confidence <= threshold:
                        color = (0, 200, 0)
                        label = f"{name} ({confidence:.0f})"
                        newly_marked = mark_attendance(name)
                        if newly_marked and name not in recognize_state["recent"]:
                            recognize_state["recent"].append(name)
                    else:
                        color = (0, 0, 255)
                        label = "Unknown"
                    cv2.rectangle(frame, (x, y), (x + w, y + h), color, 2)
                    cv2.putText(frame, label, (x, y - 10), cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)
                ok2, buf = cv2.imencode(".jpg", frame)
                if not ok2: continue
                yield (b"--frame\r\nContent-Type: image/jpeg\r\n\r\n" + buf.tobytes() + b"\r\n")
        finally:
            cam.release()

@app.route("/recognize/recent")
@login_required
def recognize_recent(): return jsonify({"recent": recognize_state["recent"]})

@app.route("/recognize/stop", methods=["POST"])
@login_required
def recognize_stop():
    recognize_state["active"] = False
    return jsonify({"ok": True})

# --- ROUTE RESET (PADAM TERUS DARI CSV) ---
@app.route("/recognize/reset", methods=["POST"])
@login_required
def recognize_reset():
    recognize_state["recent"] = []
    delete_all_records() # Padam semua rekod di dalam attendance.csv
    return jsonify({"ok": True})
# ------------------------------------------

@app.route("/attendance/download")
@login_required
def attendance_download():
    if not os.path.exists(ATTENDANCE_FILE):
        flash("No attendance records yet.", "warning")
        if current_user.role == 'admin': return redirect(url_for("attendance"))
        return redirect(url_for("user_attendance"))
    return send_file(ATTENDANCE_FILE, as_attachment=True, download_name="attendance.csv")

# ==========================================
# ROUTE BUANG REKOD (DIKONGSI ADMIN & USER)
# ==========================================
@app.route("/attendance/delete", methods=["POST"])
@login_required
def attendance_delete():
    data = request.get_json(silent=True) or {}
    ok = delete_record(data.get("name"), data.get("date"), data.get("time"))
    return jsonify({"ok": ok})

@app.route("/attendance/delete_all", methods=["POST"])
@login_required
@admin_required
def attendance_delete_all():
    delete_all_records()
    return jsonify({"ok": True})

# ==========================================
# INIT DB & RUN
# ==========================================
with app.app_context():
    try:
        db.create_all()
        print("Berjaya disambungkan ke pangkalan data MySQL!")
    except Exception as e:
        print(f"RALAT PANGKALAN DATA: {e}")
        print("Sila pastikan XAMPP MySQL telah di-start dan database 'face_attendance' telah dicipta.")

if __name__ == "__main__":
    app.run(debug=True, threaded=True)