import face_recognition
import sys
import os
import json
import cv2
import numpy as np
import pickle
import time # <--- TAMBAHAN 1

# --- PATH SERVER ---
CACHE_FILE = "C:/laragon/www/absensi/storage/app/public/data_wajah.pkl"
MAX_WIDTH = 400

def process_image_cv2(image_path):
    # ... (kode fungsi ini biarkan sama) ...
    try:
        img = cv2.imread(image_path)
        if img is None: return None
        h, w = img.shape[:2]
        if w > MAX_WIDTH:
            ratio = MAX_WIDTH / float(w)
            new_h = int(h * ratio)
            img = cv2.resize(img, (MAX_WIDTH, new_h), interpolation=cv2.INTER_AREA)
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        return img_rgb
    except:
        return None

def main():
    start_time = time.time() # <--- TAMBAHAN 2: MULAI HITUNG

    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Path foto login tidak dikirim."}))
        return
    foto_login_path = sys.argv[1]

    # ... (Validasi file cache & foto login biarkan sama) ...
    # Pastikan bagian BACA CACHE tetap ada:
    if not os.path.exists(CACHE_FILE):
        print(json.dumps({"status": "error", "message": "Cache belum ada."}))
        return

    # ... (Proses Login Image biarkan sama) ...
    login_image = process_image_cv2(foto_login_path)
    # ... check login_image is None ...

    try:
        login_encodings = face_recognition.face_encodings(login_image)
        if len(login_encodings) == 0:
            print(json.dumps({"status": "error", "message": "Wajah tidak terdeteksi."}))
            return
        
        login_encoding_to_check = login_encodings[0]

        # LOAD DATA (Disini kuncinya)
        with open(CACHE_FILE, "rb") as f:
            data = pickle.load(f)
        
        # BANDINGKAN
        distances = face_recognition.face_distance(data["encodings"], login_encoding_to_check)
        best_match_index = np.argmin(distances)
        best_match_distance = distances[best_match_index]

        end_time = time.time() # <--- TAMBAHAN 3: SELESAI HITUNG
        durasi = end_time - start_time
        
        # Masukkan info waktu ke dalam pesan output biar muncul di JSON
        info_waktu = f"(Proses Server: {durasi:.4f} detik)"

        if best_match_distance < 0.50: 
            nik_ketemu = data["niks"][best_match_index]
            # Kirim status success + info waktu
            print(json.dumps({"status": "success", "nik": nik_ketemu, "message": "Login Berhasil " + info_waktu}))
        else:
            print(json.dumps({"status": "error", "message": "Wajah tidak dikenali. " + info_waktu}))

    except Exception as e:
        print(json.dumps({"status": "error", "message": "System Error: " + str(e)}))

if __name__ == "__main__":
    main()