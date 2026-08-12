-- Alter outlets table
ALTER TABLE outlets 
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL AFTER nama_outlet,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL AFTER latitude,
ADD COLUMN IF NOT EXISTS radius_meter INT NOT NULL DEFAULT 50 AFTER longitude;

-- Alter karyawan table
ALTER TABLE karyawan 
ADD COLUMN IF NOT EXISTS foto_referensi VARCHAR(255) NULL AFTER id_outlet,
ADD COLUMN IF NOT EXISTS face_descriptor TEXT NULL AFTER foto_referensi;

-- Alter absensi table
ALTER TABLE absensi 
ADD COLUMN IF NOT EXISTS foto_masuk VARCHAR(255) NULL AFTER waktu_masuk,
ADD COLUMN IF NOT EXISTS foto_pulang VARCHAR(255) NULL AFTER waktu_pulang,
ADD COLUMN IF NOT EXISTS lat_masuk DECIMAL(10, 8) NULL AFTER foto_masuk,
ADD COLUMN IF NOT EXISTS long_masuk DECIMAL(11, 8) NULL AFTER lat_masuk,
ADD COLUMN IF NOT EXISTS lat_pulang DECIMAL(10, 8) NULL AFTER foto_pulang,
ADD COLUMN IF NOT EXISTS long_pulang DECIMAL(11, 8) NULL AFTER lat_pulang,
ADD COLUMN IF NOT EXISTS status_verifikasi ENUM('valid', 'invalid') NOT NULL DEFAULT 'valid' AFTER long_pulang;
