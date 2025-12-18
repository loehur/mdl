# 📋 Panduan Menu Navigation (UserLayout.vue)

## ✨ Konsep Data-Driven Menu

Menu navigasi sekarang menggunakan **data-driven approach**, artinya semua menu didefinisikan dalam array di JavaScript, lalu di-loop secara otomatis.

## 🔍 Keuntungan Sistem Ini:

1. ✅ **DRY (Don't Repeat Yourself)** - Tidak perlu duplikasi kode
2. ✅ **Konsistensi** - Mobile & Desktop selalu sama
3. ✅ **Mudah Maintenance** - Edit sekali, berlaku di semua tempat
4. ✅ **Scalable** - Gampang menambah/mengurangi menu

---

## 📝 Struktur Data Menu

### 1. **menuItems** (Main Menu)
Berisi menu utama aplikasi. Contoh struktur:

```javascript
const menuItems = computed(() => [
  {
    path: '/order',           // Route path
    label: 'Order',           // Nama menu yang ditampilkan
    icon: '<path ... />',     // SVG path untuk icon
    exactMatch: true          // Opsional: untuk exact path matching
  },
  {
    path: '/performance',
    label: 'Kinerja',
    icon: '<path ... />'
  },
  {
    type: 'dropdown',         // Tipe khusus untuk dropdown
    label: 'Master Data',
    icon: '<path ... />',
    children: 'masterDataItems'  // Reference ke computed property lain
  }
]);
```

### 2. **archiveItems** (Archive Menu)
Menu untuk halaman arsip dan settings. Bisa conditional (misal hanya admin):

```javascript
const archiveItems = computed(() => {
  const items = [
    {
      path: '/archive/orders',
      label: 'Order Selesai',
      icon: '<path ... />'
    }
  ];
  
  if (isAdmin()) {
    items.push({
      path: '/settings',
      label: 'Settings',
      icon: '<path ... />'
    });
  }
  
  return items;
});
```

### 3. **masterDataItems** (Submenu Master Data)
Submenu untuk dropdown Master Data:

```javascript
const masterDataItems = computed(() => {
  const items = [
    { path: '/products', label: 'Produk', icon: '...' },
    { path: '/worksteps', label: 'Langkah Kerja', icon: '...' },
    { path: '/customers', label: 'Pelanggan', icon: '...' },
    { path: '/therapists', label: 'Terapis', icon: '...' }
  ];

  if (isAdmin()) {
    items.push({ path: '/users', label: 'Users', icon: '...' });
  }

  return items;
});
```

---

## 🛠️ Cara Mengubah Urutan Menu

**SEKARANG JAUH LEBIH MUDAH!** Cukup ubah urutan di array `menuItems`:

```javascript
// SEBELUM (Master Data di atas)
const menuItems = computed(() => [
  { path: '/order', label: 'Order', ... },
  { type: 'dropdown', label: 'Master Data', ... },  // ← Di sini
  { path: '/performance', label: 'Kinerja', ... },
  { path: '/cash-flow', label: 'Laporan Kas', ... }
]);

// SESUDAH (Master Data di bawah)
const menuItems = computed(() => [
  { path: '/order', label: 'Order', ... },
  { path: '/performance', label: 'Kinerja', ... },
  { path: '/cash-flow', label: 'Laporan Kas', ... },
  { type: 'dropdown', label: 'Master Data', ... }  // ← Pindah ke sini
]);
```

**Otomatis berubah di Mobile & Desktop!** 🎉

---

## ➕ Cara Menambah Menu Baru

### Menambah menu regular:

```javascript
const menuItems = computed(() => [
  // ... menu lainnya
  {
    path: '/reports',
    label: 'Laporan',
    icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>'
  }
]);
```

### Menambah submenu baru di Master Data:

```javascript
const masterDataItems = computed(() => {
  const items = [
    // ... items lainnya
    {
      path: '/staff',
      label: 'Staff',
      icon: '<path ... />'
    }
  ];
  return items;
});
```

---

## 🎨 Icon SVG

Icon menggunakan **Heroicons** (stroke-based). Cari icon di:
- 🌐 [heroicons.com](https://heroicons.com)
- Copy **hanya path** dari SVG, bukan keseluruhan `<svg>`

Contoh:
```html
<!-- Full SVG dari Heroicons -->
<svg viewBox="0 0 24 24" fill="none">
  <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16"/>
</svg>

<!-- Yang di-copy hanya: -->
"<path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M4 6h16M4 12h16\"/>"
```

---

## 🔒 Menu Conditional (Berdasarkan Role)

Gunakan `if` statement di dalam computed:

```javascript
const menuItems = computed(() => {
  const items = [
    { path: '/order', label: 'Order', ... }
  ];
  
  // Hanya tampilkan untuk admin
  if (isAdmin()) {
    items.push({
      path: '/admin-panel',
      label: 'Admin Panel',
      icon: '...'
    });
  }
  
  return items;
});
```

---

## 🚀 Template Structure

Template menggunakan `v-for` untuk loop menu:

```vue
<template v-for="item in menuItems" :key="item.path || item.label">
  <!-- Regular Menu Link -->
  <router-link v-if="!item.type" :to="item.path">
    <svg v-html="item.icon"></svg>
    {{ item.label }}
  </router-link>

  <!-- Dropdown Menu -->
  <div v-else-if="item.type === 'dropdown'">
    <button>{{ item.label }}</button>
    <div v-show="showMasterDropdown">
      <!-- Submenu items -->
    </div>
  </div>
</template>
```

---

## 📌 Best Practices

1. ✅ **Selalu gunakan computed()** untuk reactive menu
2. ✅ **Gunakan key yang unik** di v-for (`:key="item.path"`)
3. ✅ **Pisahkan menu berdasarkan kategori** (main, archive, etc)
4. ✅ **Gunakan conditional rendering** untuk role-based menu
5. ✅ **Test di mobile & desktop** setelah perubahan

---

## 🆘 Troubleshooting

### Menu tidak muncul?
- ✓ Cek apakah array tidak kosong
- ✓ Cek computed property sudah return items
- ✓ Periksa console untuk error

### Menu duplikat di mobile & desktop?
- ✓ Pastikan template menggunakan dynamic loop
- ✓ Jangan hardcode menu items

### Icon tidak muncul?
- ✓ Pastikan menggunakan `v-html="item.icon"`
- ✓ Icon harus berupa string SVG path

---

**Author**: Refactored on 2025-12-18  
**File**: `src/user_area/UserLayout.vue`
