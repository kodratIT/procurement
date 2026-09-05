---
paths:
  - 'app/Filament/Resources/ApprovalInbox/**,app/Filament/Resources/ApprovalInboxResource.php'
---

# Resources

## Pisahkan approval aktif dan arsip pribadi
Daftar Approval memakai tab Aktif sebagai default untuk tugas yang sedang menunggu tindakan. Tab Arsip hanya memuat PR yang pernah diberi aksi approve oleh user login; PR berstatus rejected, returned, atau cancelled tidak masuk arsip. Aksi approval hanya tersedia pada tugas aktif.
