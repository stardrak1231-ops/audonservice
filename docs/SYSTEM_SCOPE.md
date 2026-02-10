# 📋 ขอบเขตระบบ "อู่อุดร Service"
## ระบบจัดการอู่ซ่อมรถ (Auto Repair Shop Management System)

---

## 🎯 ภาพรวมระบบ

ระบบเว็บแอปพลิเคชันสำหรับจัดการอู่ซ่อมรถแบบครบวงจร รองรับ 4 กลุ่มผู้ใช้งาน ครอบคลุมตั้งแต่การรับรถเข้าซ่อม จนถึงส่งมอบรถคืนลูกค้า

### เทคโนโลยีที่ใช้
| ส่วน | เทคโนโลยี |
|------|-----------|
| Backend | PHP 8.2 |
| Database | MariaDB 10.4 |
| Frontend | HTML, Tailwind CSS, JavaScript |
| Server | XAMPP (Apache) |

---

## 👥 กลุ่มผู้ใช้งาน (4 Portals)

### 1. 🏠 หน้าเว็บหลัก (Public)
**URL:** `/model01/`

| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | หน้าแรก แสดงบริการ ข่าวสาร โปรโมชั่น |
| `login.php` | เข้าสู่ระบบสมาชิก |
| `register.php` | สมัครสมาชิกใหม่ + ลงทะเบียนรถ |
| `staff-login.php` | เข้าสู่ระบบพนักงาน |
| `logout.php` | ออกจากระบบ |

---

### 2. 👔 ระบบ Admin (ผู้ดูแลระบบ)
**URL:** `/model01/admin/`
**สิทธิ์:** role = 'admin'

#### 📊 Dashboard
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | แดชบอร์ดสรุปภาพรวม (งานวันนี้ รายได้ สต็อก) |

#### 👥 จัดการสมาชิก (`/members/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการสมาชิก ค้นหา กรอง |
| `view.php` | ดูรายละเอียดสมาชิก + รถ + ประวัติงาน |

#### 🚗 จัดการงาน (`/jobs/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการงานทั้งหมด กรองสถานะ |
| `create.php` | เปิดงานใหม่ (ซ่อม/บริการ) |
| `view.php` | รายละเอียดงาน เปลี่ยนสถานะ |

#### 🔩 จัดการอะไหล่ (`/parts/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการอะไหล่ เพิ่ม/แก้ไข/ลบ จัดการสต็อก |

#### 🛠️ จัดการบริการ (`/services/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการบริการ/ค่าแรง เพิ่ม/แก้ไข/ลบ |

#### 📦 ใบสั่งซื้อ (`/po/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการใบสั่งซื้อ + ผู้จำหน่าย |
| `create.php` | สร้างใบสั่งซื้อใหม่ |
| `view.php` | รายละเอียด PO รับของ |

#### 👤 จัดการผู้ใช้ (`/users/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการพนักงาน เพิ่ม/แก้ไข/ระงับ |

#### 📢 โปรโมชั่น
| หน้า | ฟังก์ชัน |
|------|----------|
| `promotions.php` | จัดการข่าว/โปรโมชั่น |

#### 📈 รายงาน (`/reports/`)
| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายงานรายได้ตามช่วงวัน |
| `jobs.php` | รายงานสถิติงาน |
| `revenue.php` | รายงานรายได้ละเอียด |
| `parts.php` | รายงานอะไหล่ขายดี |
| `members.php` | รายงานสมาชิก VIP |

#### ⚙️ ตั้งค่า
| หน้า | ฟังก์ชัน |
|------|----------|
| `settings.php` | ตั้งค่า VIP Threshold + บัญชีรับเงิน |

---

### 3. 💰 ระบบ Accounting (ฝ่ายบัญชี)
**URL:** `/model01/accounting/`
**สิทธิ์:** role = 'accountant'

| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | แดชบอร์ด รายการงานรอออกบิล |
| `invoice.php` | ออกใบเสร็จ + รับชำระเงิน (เงินสด/โอน) |
| `payments.php` | ประวัติการชำระเงิน |
| `reports.php` | รายงานรายได้ |

---

### 4. 🔧 ระบบ Technician (ฝ่ายช่าง)
**URL:** `/model01/technician/`
**สิทธิ์:** role = 'technician'

| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | รายการงาน แยก "งานที่ต้องทำ" vs "งานส่งมอบแล้ว" |
| `job.php` | รายละเอียดงาน: เปลี่ยนสถานะ เพิ่มบริการ เบิกอะไหล่ อัปโหลดรูป บันทึกการทำงาน |

**หมายเหตุ:** งานที่สถานะ COMPLETED/DELIVERED จะซ่อนฟอร์มเพิ่มทั้งหมด

---

### 5. 🚗 ระบบ Member (สมาชิก/ลูกค้า)
**URL:** `/model01/member/`
**สิทธิ์:** member login

| หน้า | ฟังก์ชัน |
|------|----------|
| `index.php` | งานปัจจุบัน (รถที่อยู่ในอู่) |
| `job.php` | รายละเอียดงาน สถานะ รูปภาพ ค่าใช้จ่าย |
| `history.php` | ประวัติงานทั้งหมด กรองตามรถ/ประเภท |
| `receipt.php` | ดูใบเสร็จ พิมพ์ได้ |
| `profile.php` | แก้ไขข้อมูลส่วนตัว เปลี่ยนรหัสผ่าน |

---

## 🗄️ โครงสร้างฐานข้อมูล (19 ตาราง)

### Core Tables
| ตาราง | คำอธิบาย |
|-------|----------|
| `users` | พนักงาน (admin, technician, accountant) |
| `members` | สมาชิก/ลูกค้า |
| `vehicles` | รถของสมาชิก |
| `settings` | ตั้งค่าระบบ (VIP, บัญชีธนาคาร) |

### Job Management
| ตาราง | คำอธิบาย |
|-------|----------|
| `job_orders` | ใบงาน |
| `job_status` | สถานะงาน (lookup table) |
| `job_services` | บริการในงาน |
| `job_parts` | อะไหล่ที่ใช้ในงาน |
| `job_timeline` | ประวัติเปลี่ยนสถานะ |
| `job_photos` | รูปภาพงาน (before/during/after) |
| `job_notes` | บันทึกการทำงาน |

### Inventory
| ตาราง | คำอธิบาย |
|-------|----------|
| `service_items` | รายการบริการ/ค่าแรง |
| `spare_parts` | อะไหล่ (รองรับหน่วยซื้อ/ขายต่างกัน) |
| `stock_movements` | ประวัติเคลื่อนไหวสต็อก |

### Purchasing
| ตาราง | คำอธิบาย |
|-------|----------|
| `suppliers` | ผู้จำหน่าย |
| `purchase_orders` | ใบสั่งซื้อ |
| `purchase_order_items` | รายการในใบสั่งซื้อ |

### Finance
| ตาราง | คำอธิบาย |
|-------|----------|
| `invoices` | ใบเสร็จ (รวมส่วนลด VIP/Promo) |
| `payments` | การชำระเงิน (เงินสด/โอน + เลขอ้างอิง) |

### Others
| ตาราง | คำอธิบาย |
|-------|----------|
| `promotions` | ข่าว/โปรโมชั่น |

---

## 🔄 Workflow หลัก

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          REPAIR WORKFLOW                                │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ลูกค้า        Admin          ช่าง           บัญชี          ลูกค้า       │
│    │            │              │              │              │          │
│    │  เข้ารับรถ │              │              │              │          │
│    ├───────────►│              │              │              │          │
│    │            │ เปิดงาน      │              │              │          │
│    │            ├─────────────►│              │              │          │
│    │            │              │ รับรถ        │              │          │
│    │            │              │ ตรวจอาการ    │              │          │
│    │            │              │ รออะไหล่     │              │          │
│    │            │              │ ซ่อม         │              │          │
│    │            │              │ เสร็จ        │              │          │
│    │            │              ├─────────────►│              │          │
│    │            │              │              │ ออกบิล       │          │
│    │            │              │              │ รับชำระ      │          │
│    │            │              │              ├─────────────►│          │
│    │            │              │              │              │ รับรถกลับ │
│    │◄───────────┼──────────────┼──────────────┼──────────────┤          │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### สถานะงาน (Job Status)

#### งานซ่อม (Repair)
```
RECEIVED → INSPECTING → WAIT_PART → IN_PROGRESS → COMPLETED → WAIT_PAYMENT → DELIVERED
```

#### งานบริการ (Service)
```
RECEIVED → IN_PROGRESS → COMPLETED → WAIT_PAYMENT → DELIVERED
```

---

## ✨ ฟีเจอร์เด่น

| ฟีเจอร์ | รายละเอียด |
|---------|------------|
| **VIP System** | สมาชิกที่จ่ายครบ 50,000 บาทขึ้นไป ได้ส่วนลด 5% |
| **Multi-unit Stock** | อะไหล่รองรับหน่วยซื้อ/ขายต่างกัน (เช่น ซื้อเป็นกล่อง ขายเป็นขวด) |
| **Job Timeline** | บันทึกประวัติทุกการเปลี่ยนสถานะ |
| **Before/After Photos** | ช่างอัปโหลดรูปก่อน/ระหว่าง/หลังซ่อม |
| **Dual Payment** | รับชำระเงินสด + โอน (พร้อม PromptPay QR) |
| **Printable Receipt** | สมาชิกดูและพิมพ์ใบเสร็จได้ |
| **Role-based Access** | แยกสิทธิ์ตาม Admin/Accountant/Technician/Member |
| **Responsive Design** | ใช้งานได้บนมือถือ |

---

## 📁 โครงสร้างไฟล์

```
model01/
├── index.php              # หน้าแรก
├── login.php              # Login สมาชิก
├── register.php           # สมัครสมาชิก
├── staff-login.php        # Login พนักงาน
├── logout.php             # Logout
├── project_db.sql         # Database schema
│
├── config/
│   ├── database.php       # DB connection
│   └── session.php        # Session management
│
├── admin/                 # Admin Portal
│   ├── index.php          # Dashboard
│   ├── settings.php       # Settings
│   ├── promotions.php     # Promotions
│   ├── jobs/              # Job management
│   ├── members/           # Member management
│   ├── parts/             # Parts management
│   ├── services/          # Service management
│   ├── po/                # Purchase Orders
│   ├── users/             # User management
│   └── reports/           # Reports
│
├── accounting/            # Accounting Portal
│   ├── index.php          # Dashboard
│   ├── invoice.php        # Invoice & Payment
│   ├── payments.php       # Payment history
│   └── reports.php        # Reports
│
├── technician/            # Technician Portal
│   ├── index.php          # Job list
│   └── job.php            # Job detail
│
├── member/                # Member Portal
│   ├── index.php          # Current jobs
│   ├── job.php            # Job detail
│   ├── history.php        # Job history
│   ├── receipt.php        # Receipt view
│   └── profile.php        # Profile edit
│
└── uploads/               # Uploaded files
    ├── jobs/              # Job photos
    ├── parts/             # Part images
    ├── profiles/          # Profile images
    └── vehicles/          # Vehicle images
```

---

## 🔐 ความปลอดภัย

| มาตรการ | รายละเอียด |
|---------|------------|
| Password Hashing | bcrypt (password_hash) |
| Session Management | PHP Sessions + role check |
| SQL Injection | PDO Prepared Statements |
| XSS Prevention | htmlspecialchars() |
| Access Control | requireMemberLogin() / requireStaffLogin() |

---

## 📊 สรุป

| รายการ | จำนวน |
|--------|-------|
| Portals | 4 (Admin, Accounting, Technician, Member) |
| PHP Files | ~35 ไฟล์ |
| Database Tables | 19 ตาราง |
| User Roles | 4 (admin, accountant, technician, member) |

---

*เอกสารนี้สร้างเมื่อ: 20 มกราคม 2569*
