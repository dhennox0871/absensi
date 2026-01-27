import face_recognition
import os
import pickle
import sys
import cv2
import numpy as np

# --- KONFIGURASI PATH (SERVER LOKAL) ---
# Karena script ini jalan di server, dia tetap pakai path lokal laptop
BASE_DIR = "C:/laragon/www/absensi/storage/app/public"
FACES_DIR = os.path.join(BASE_DIR, "faces_db")
CACHE_FILE = os.path.join(BASE_DIR, "data_wajah.pkl")

def process_image_cv2(image_path):
    try:
        # Baca gambar pakai OpenCV biar cepat & kompatibel
        img = cv2.imread(image_path)
        if img is None: return None
        
        # Convert ke RGB (Wajib buat Dlib)
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        return img_rgb
    except:
        return None

def main():
    print("--- MEMULAI PROSES TRAINING WAJAH ---")
    
    if not os.path.exists(FACES_DIR):
        print(f"Error: Folder {FACES_DIR} tidak ditemukan.")
        return

    known_encodings = []
    known_niks = []
    
    files = os.listdir(FACES_DIR)
    total_files = len(files)
    processed = 0
    success = 0
    
    for filename in files:
        if filename.lower().endswith(('.png', '.jpg', '.jpeg')):
            processed += 1
            path = os.path.join(FACES_DIR, filename)
            
            try:
                # 1. Load Gambar
                image = process_image_cv2(path)
                if image is None: continue

                # 2. Deteksi Wajah (Encoding)
                # Kita pakai model 'hog' biar cepat, num_jitters=1 (standar)
                encodings = face_recognition.face_encodings(image)
                
                if len(encodings) > 0:
                    # Ambil NIK dari nama file (5001_1.jpg -> 5001)
                    nik = filename.split('_')[0]
                    
                    known_encodings.append(encodings[0])
                    known_niks.append(nik)
                    success += 1
                    # print(f"[{processed}/{total_files}] OK: {filename}") # Uncomment jika ingin log detail
            except Exception as e:
                print(f"Gagal {filename}: {e}")
                continue

    # 3. SIMPAN KE FILE PKL
    data = {"encodings": known_encodings, "niks": known_niks}
    
    try:
        with open(CACHE_FILE, "wb") as f:
            pickle.dump(data, f)
        print(f"SUKSES! {success} wajah berhasil disimpan ke {CACHE_FILE}")
    except Exception as e:
        print(f"GAGAL MENYIMPAN CACHE: {e}")

if __name__ == "__main__":
    main()