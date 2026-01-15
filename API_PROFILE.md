# API Documentation - Profile Management

## Base URL
```
http://localhost:8000/api
```

## Authentication
Semua endpoint profile memerlukan authentication token di header:
```
Authorization: Bearer {your_token}
```

---

## Endpoints

### 1. Get Profile
Mendapatkan profil user yang sedang login.

**Endpoint:**
```http
GET /profile
```

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "email": "siswa@example.com",
    "nama": "Ahmad Siswa",
    "role": "siswa",
    "avatar": "http://localhost:8000/storage/avatars/abc123.jpg",
    "kelas": "XII",
    "jurusan_id": "JUR-1",
    "nis": "12345",
    "nip": null,
    "created_at": "2026-01-13T10:00:00.000000Z"
  }
}
```

**Notes:**
- Avatar akan `null` jika belum diupload
- Field `nis` hanya ada untuk role siswa
- Field `nip` hanya ada untuk role guru
- Field `kelas` dan `jurusan_id` hanya ada untuk role siswa

---

### 2. Update Profile
Update profil user (nama, avatar, kelas, jurusan).

**Endpoint:**
```http
PUT /profile
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body (multipart/form-data):**
```
nama: "Updated Name"
avatar: [image file]
kelas: "XII"              (optional, siswa only)
jurusan_id: "JUR-1"       (optional, siswa only)
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Profil berhasil diupdate",
  "data": {
    "id": 1,
    "email": "siswa@example.com",
    "nama": "Updated Name",
    "role": "siswa",
    "avatar": "http://localhost:8000/storage/avatars/new-avatar.jpg",
    "kelas": "XII",
    "jurusan_id": "JUR-1",
    "nis": "12345",
    "nip": null
  }
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "avatar": ["Avatar harus berformat jpeg, jpg, atau png"],
    "nama": ["Nama maksimal 255 karakter"]
  }
}
```

**Validation Rules:**
- `nama`: optional, string, max 255 characters
- `avatar`: optional, image (jpeg/jpg/png), max 2MB
- `kelas`: optional, must be X, XI, or XII (siswa only)
- `jurusan_id`: optional, must exist in jurusans table (siswa only)

**Notes:**
- Jika upload avatar baru, avatar lama akan dihapus otomatis
- Kelas dan jurusan_id hanya bisa diupdate oleh siswa
- Gunakan `multipart/form-data` untuk upload file

---

### 3. Change Password
Mengubah password user.

**Endpoint:**
```http
PUT /profile/password
```

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "current_password": "oldpassword123",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Password berhasil diubah. Silakan login kembali"
}
```

**Response Error (400):**
```json
{
  "success": false,
  "message": "Password lama tidak sesuai",
  "errors": {
    "current_password": ["Password lama tidak sesuai"]
  }
}
```

**Validation Rules:**
- `current_password`: required, string
- `new_password`: required, string, min 8 characters, must match confirmation
- `new_password_confirmation`: required, must match new_password

**Important Notes:**
- ⚠️ **Setelah berhasil ubah password, semua token akan di-revoke**
- User harus **login kembali** setelah change password
- Ini untuk keamanan agar device lain yang pakai token lama tidak bisa akses lagi

---

## JavaScript/Axios Examples

### Get Profile
```javascript
const getProfile = async () => {
  try {
    const response = await axios.get('/api/profile', {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    console.log('Profile:', response.data.data);
    return response.data.data;
  } catch (error) {
    console.error('Error:', error.response.data);
  }
};
```

### Update Profile with Avatar
```javascript
const updateProfile = async (nama, avatarFile) => {
  try {
    const formData = new FormData();
    formData.append('nama', nama);
    
    if (avatarFile) {
      formData.append('avatar', avatarFile);
    }
    
    const response = await axios.put('/api/profile', formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'multipart/form-data'
      }
    });
    
    console.log('Updated:', response.data.message);
    return response.data.data;
  } catch (error) {
    console.error('Validation errors:', error.response.data.errors);
  }
};

// Usage:
// const fileInput = document.querySelector('input[type="file"]');
// updateProfile('New Name', fileInput.files[0]);
```

### Update Profile (Siswa - with kelas & jurusan)
```javascript
const updateSiswaProfile = async (nama, kelas, jurusanId, avatarFile) => {
  try {
    const formData = new FormData();
    formData.append('nama', nama);
    formData.append('kelas', kelas);
    formData.append('jurusan_id', jurusanId);
    
    if (avatarFile) {
      formData.append('avatar', avatarFile);
    }
    
    const response = await axios.put('/api/profile', formData, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'multipart/form-data'
      }
    });
    
    return response.data.data;
  } catch (error) {
    console.error('Error:', error.response.data);
  }
};
```

### Change Password
```javascript
const changePassword = async (currentPassword, newPassword, newPasswordConfirmation) => {
  try {
    const response = await axios.put('/api/profile/password', {
      current_password: currentPassword,
      new_password: newPassword,
      new_password_confirmation: newPasswordConfirmation
    }, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    console.log('Success:', response.data.message);
    
    // Important: Logout user after password change
    localStorage.removeItem('token');
    window.location.href = '/login';
    
  } catch (error) {
    if (error.response.data.errors?.current_password) {
      alert('Password lama tidak sesuai');
    } else if (error.response.data.errors?.new_password) {
      alert('Password baru tidak valid');
    }
  }
};
```

---

## React Example (with useState)

### Profile Page Component
```jsx
import { useState, useEffect } from 'react';
import axios from 'axios';

function ProfilePage() {
  const [profile, setProfile] = useState(null);
  const [nama, setNama] = useState('');
  const [avatar, setAvatar] = useState(null);
  const [loading, setLoading] = useState(false);
  
  const token = localStorage.getItem('token');

  // Load profile on mount
  useEffect(() => {
    const fetchProfile = async () => {
      try {
        const response = await axios.get('/api/profile', {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        setProfile(response.data.data);
        setNama(response.data.data.nama);
      } catch (error) {
        console.error('Error loading profile:', error);
      }
    };
    
    fetchProfile();
  }, [token]);

  // Handle avatar change
  const handleAvatarChange = (e) => {
    const file = e.target.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
      alert('Avatar maksimal 2MB');
      return;
    }
    setAvatar(file);
  };

  // Submit update
  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    const formData = new FormData();
    formData.append('nama', nama);
    if (avatar) {
      formData.append('avatar', avatar);
    }
    
    try {
      const response = await axios.put('/api/profile', formData, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'multipart/form-data'
        }
      });
      
      setProfile(response.data.data);
      alert('Profil berhasil diupdate!');
      setAvatar(null);
    } catch (error) {
      console.error('Update error:', error.response.data);
      alert('Gagal update profil');
    } finally {
      setLoading(false);
    }
  };

  if (!profile) return <div>Loading...</div>;

  return (
    <div className="profile-page">
      <h1>Edit Profil</h1>
      
      <div className="avatar-preview">
        {profile.avatar && <img src={profile.avatar} alt="Avatar" />}
      </div>
      
      <form onSubmit={handleSubmit}>
        <div>
          <label>Nama:</label>
          <input
            type="text"
            value={nama}
            onChange={(e) => setNama(e.target.value)}
          />
        </div>
        
        <div>
          <label>Avatar:</label>
          <input
            type="file"
            accept="image/jpeg,image/jpg,image/png"
            onChange={handleAvatarChange}
          />
          <small>Max 2MB (jpeg, jpg, png)</small>
        </div>
        
        <button type="submit" disabled={loading}>
          {loading ? 'Updating...' : 'Update Profil'}
        </button>
      </form>
    </div>
  );
}

export default ProfilePage;
```

### Change Password Component
```jsx
import { useState } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

function ChangePasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [newPasswordConfirmation, setNewPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  
  const navigate = useNavigate();
  const token = localStorage.getItem('token');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    
    try {
      await axios.put('/api/profile/password', {
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: newPasswordConfirmation
      }, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      // Password changed, logout and redirect
      alert('Password berhasil diubah! Silakan login kembali.');
      localStorage.removeItem('token');
      navigate('/login');
      
    } catch (error) {
      if (error.response?.data?.errors?.current_password) {
        setError('Password lama tidak sesuai');
      } else if (error.response?.data?.errors?.new_password) {
        setError('Password baru minimal 8 karakter');
      } else {
        setError('Terjadi kesalahan');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="change-password-form">
      <h2>Ubah Password</h2>
      
      {error && <div className="error-message">{error}</div>}
      
      <form onSubmit={handleSubmit}>
        <div>
          <label>Password Lama:</label>
          <input
            type="password"
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            required
          />
        </div>
        
        <div>
          <label>Password Baru:</label>
          <input
            type="password"
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            required
            minLength={8}
          />
        </div>
        
        <div>
          <label>Konfirmasi Password Baru:</label>
          <input
            type="password"
            value={newPasswordConfirmation}
            onChange={(e) => setNewPasswordConfirmation(e.target.value)}
            required
          />
        </div>
        
        <button type="submit" disabled={loading}>
          {loading ? 'Mengubah...' : 'Ubah Password'}
        </button>
      </form>
    </div>
  );
}

export default ChangePasswordForm;
```

---

## Database Schema

### users table
```sql
id: bigint (PK)
name: varchar(255)               # Accessible as 'nama' via accessor
email: varchar(255) UNIQUE
password: varchar(255) HASHED
role: enum('admin','guru','siswa')
nisn: varchar(255) UNIQUE        # For siswa
nis: varchar(255)                # For siswa
nip: varchar(255) UNIQUE         # For guru
kelas: varchar(10)               # X, XI, XII (siswa only)
jurusan_id: varchar(10)          # FK to jurusans
phone: varchar(20)
address: text
avatar: varchar(255)             # Path to avatar file
is_active: boolean
created_at: timestamp
updated_at: timestamp
```

**Avatar Storage:**
- Disimpan di: `storage/app/public/avatars/`
- Accessible via: `http://localhost:8000/storage/avatars/{filename}`
- Max size: 2MB
- Allowed formats: jpeg, jpg, png

---

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "avatar": ["Avatar harus berformat jpeg, jpg, atau png"],
    "nama": ["Nama maksimal 255 karakter"],
    "new_password": ["Password baru minimal 8 karakter"]
  }
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

---

## Testing dengan Postman/Thunder Client

### 1. Get Profile
```
GET http://localhost:8000/api/profile
Headers:
  Authorization: Bearer {your_token}
```

### 2. Update Profile
```
PUT http://localhost:8000/api/profile
Headers:
  Authorization: Bearer {your_token}
  Content-Type: multipart/form-data
Body (form-data):
  nama: "New Name"
  avatar: [select file]
```

### 3. Change Password
```
PUT http://localhost:8000/api/profile/password
Headers:
  Authorization: Bearer {your_token}
  Content-Type: application/json
Body (raw JSON):
{
  "current_password": "password",
  "new_password": "newpassword123",
  "new_password_confirmation": "newpassword123"
}
```

---

## Important Notes

1. **Avatar Upload:**
   - Max file size: 2MB
   - Allowed formats: jpeg, jpg, png
   - Old avatar will be deleted automatically when uploading new one
   - Avatar URL includes full path: `http://localhost:8000/storage/avatars/...`

2. **Change Password Security:**
   - All user tokens will be revoked after successful password change
   - User must login again with new password
   - This ensures old sessions/devices cannot access account anymore

3. **Field Mapping:**
   - Database uses `name` but API returns `nama`
   - This is handled by accessor/mutator in User model
   - Always use `nama` in API requests/responses

4. **Role-Specific Fields:**
   - `nis`, `kelas`, `jurusan_id`: Siswa only
   - `nip`: Guru only
   - Admin doesn't have nis/nip/kelas/jurusan

5. **Storage Link:**
   - Make sure to run `php artisan storage:link` to create symbolic link
   - This makes `storage/app/public` accessible via `public/storage`

---

## Summary

**Total Profile Endpoints: 3**
- ✅ GET `/profile` - Get current user profile
- ✅ PUT `/profile` - Update profile (nama, avatar, kelas, jurusan)
- ✅ PUT `/profile/password` - Change password

**Total API Endpoints: 69** (from 66 previously)

**Features:**
- ✅ Avatar upload with file validation
- ✅ Auto-delete old avatar on update
- ✅ Password change with current password verification
- ✅ Token revocation after password change (security)
- ✅ Role-specific field updates (siswa only for kelas/jurusan)
- ✅ Proper error handling and validation messages
- ✅ Indonesian error messages

Backend Jagat Kawruh siap untuk frontend integration! 🎉
