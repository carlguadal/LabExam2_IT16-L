# Backup & Recovery Proposal - Student Management System

## 1. BACKUP STRATEGY

### 1.1 Database Backups
- **Type**: Full MySQL database dumps (infosec_lab)
- **Frequency**: Daily automated backups at 2:00 AM (off-peak hours)
- **Retention**: Keep 7-day rolling backup (latest 7 daily backups)
- **Location**: `C:\xampp\backups\database\`
- **Format**: SQL compressed (.sql.gz)

### 1.2 Application Files Backup
- **Scope**: PHP files, CSS, configuration files (excluding logs)
- **Frequency**: Weekly automated backups (Every Sunday at 3:00 AM)
- **Retention**: Keep 4-week rolling backup (latest 4 weekly backups)
- **Location**: `C:\xampp\backups\application\`
- **Format**: ZIP compressed

### 1.3 Log Files Backup
- **Scope**: Security logs from `logs/security.log`
- **Frequency**: Monthly archive (First day of month)
- **Retention**: Keep 12-month archive
- **Location**: `C:\xampp\backups\logs\`
- **Purpose**: Compliance and security audit trails

---

## 2. BACKUP IMPLEMENTATION

### 2.1 Automated Database Backup Script
```batch
@echo off
REM Daily Database Backup Script
set BACKUP_DIR=C:\xampp\backups\database
set TIMESTAMP=%date:~10,4%-%date:~4,2%-%date:~7,2%_%time:~0,2%-%time:~5,2%
set BACKUP_FILE=%BACKUP_DIR%\infosec_lab_backup_%TIMESTAMP%.sql

REM Create backup directory if not exists
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Backup database
"C:\xampp\mysql\bin\mysqldump.exe" -u root infosec_lab > "%BACKUP_FILE%"

REM Compress backup
"C:\Program Files\7-Zip\7z.exe" a "%BACKUP_FILE%.gz" "%BACKUP_FILE%"
del "%BACKUP_FILE%"

REM Delete backups older than 7 days
forfiles /S /D +7 /P "%BACKUP_DIR%" /C "cmd /c del @file"

echo Backup completed: %BACKUP_FILE%.gz
```

### 2.2 Automated File Backup Script
```batch
@echo off
REM Weekly Application Files Backup
set BACKUP_DIR=C:\xampp\backups\application
set TIMESTAMP=%date:~10,4%-%date:~4,2%-%date:~7,2%
set BACKUP_FILE=%BACKUP_DIR%\application_backup_%TIMESTAMP%.zip

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Backup application files (exclude logs)
"C:\Program Files\7-Zip\7z.exe" a "%BACKUP_FILE%" C:\xampp\htdocs\Infosec_lab2-main -xr!logs

echo Backup completed: %BACKUP_FILE%
```

---

## 3. RECOVERY PROCEDURES

### 3.1 Database Recovery - Full Database Restore
```sql
-- 1. Stop the application (prevent new connections)
-- 2. Decompress backup file
-- 3. Run restore command:
mysql -u root infosec_lab < infosec_lab_backup_2026-03-16_02-00.sql

-- 4. Verify recovery
SELECT COUNT(*) as total_students FROM students;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_courses FROM courses;

-- 5. Restart application
```

### 3.2 Point-in-Time Recovery
- Use most recent full backup before point of failure
- Restore database from backup file
- Manually apply any pending changes if needed

### 3.3 Table-Level Recovery
```sql
-- If only specific table is corrupted, restore from backup
-- Extract table structure and data from backup file
-- Use INSERT statements for specific records if needed
```

### 3.4 Application Files Recovery
1. Decompress backup ZIP file to temporary directory
2. Verify file integrity
3. Replace corrupted/missing files
4. Test application functionality
5. Clear application cache if needed

---

## 4. DISASTER RECOVERY SCENARIO

### Scenario 1: Database Corruption
**RTO**: 1 hour | **RPO**: 24 hours (last backup)
1. Identify corrupted tables
2. Restore from latest backup
3. Run integrity checks (REPAIR TABLE)
4. Verify data consistency
5. Resume operations

### Scenario 2: Critical File Loss
**RTO**: 30 minutes | **RPO**: 1 week (last file backup)
1. Restore application files from backup
2. Verify file permissions
3. Clear PHP opcache
4. Test login functionality
5. Resume operations

### Scenario 3: Complete System Failure
**RTO**: 4 hours | **RPO**: 24 hours
1. Reinstall XAMPP/Apache/MySQL
2. Restore database from backup
3. Restore application files from backup
4. Reconfigure database connections
5. Test all functionality end-to-end
6. Resume operations

---

## 5. BACKUP VERIFICATION & TESTING

### 5.1 Weekly Backup Verification
- Verify backup files exist and have reasonable file size
- Check backup timestamps are correct
- Sample test: Extract and verify one database backup monthly

### 5.2 Monthly Restore Drill
- Perform test restore on staging/test environment
- Validate data integrity after restore
- Document any issues found
- Update recovery procedures if needed

### 5.3 Backup Integrity Checklist
- ✅ Backup file size > 100KB (indicates data presence)
- ✅ File timestamp is recent (within 24 hours for DB, 7 days for files)
- ✅ File permissions allow read access
- ✅ Compressed files can be extracted successfully

---

## 6. STORAGE & SECURITY

### 6.1 Backup Storage Locations
- **Primary**: `C:\xampp\backups\` (Local XAMPP server)
- **Secondary** (Recommended): External USB drive or cloud storage
- **Format**: Compressed and encrypted (7-Zip with password)

### 6.2 Backup Security
- Encrypt compressed backup files with strong password
- Restrict folder permissions to Administrator only
- Store offsite backup copies in secure location
- Document backup encryption passwords in secure vault

### 6.3 Storage Capacity Planning
- Database backup: ~2-5 MB per backup
- Application backup: ~1-2 MB per backup
- Monthly logs: ~0.5-1 MB
- Total storage needed: ~500 MB for 1-year retention

---

## 7. BACKUP SCHEDULE

| Item | Frequency | Time | Retention | Location |
|------|-----------|------|-----------|----------|
| Database | Daily | 2:00 AM | 7 days | `./backups/database/` |
| Application | Weekly | 3:00 AM Sunday | 4 weeks | `./backups/application/` |
| Logs | Monthly | 1st of month | 12 months | `./backups/logs/` |

---

## 8. RECOVERY TIME & OBJECTIVES

| Scenario | RTO* | RPO** | Priority |
|----------|------|-------|----------|
| Database corruption | 1 hour | 24 hours | Critical |
| File loss | 30 min | 7 days | High |
| User data loss | 2 hours | 24 hours | Critical |
| Log loss | 24 hours | 30 days | Low |

*RTO = Recovery Time Objective (max time to restore)
*RPO = Recovery Point Objective (max data loss acceptable)

---

## 9. BACKUP MAINTENANCE

### 9.1 Regular Tasks
- ✅ Monitor backup success notifications weekly
- ✅ Verify backup file sizes monthly
- ✅ Test restore procedure every 3 months
- ✅ Update backup scripts with system changes
- ✅ Document recovery procedures

### 9.2 Annual Review
- Audit all backups for completeness
- Review and update recovery procedures
- Conduct full system recovery drill
- Update RTO/RPO based on system changes
- Verify storage capacity and cleanup old backups

---

## 10. IMPLEMENTATION CHECKLIST

- [ ] Create backup directories (`C:\xampp\backups\database`, `application`, `logs`)
- [ ] Schedule Task Scheduler jobs for automated backups
- [ ] Test backup scripts independently
- [ ] Document backup encryption passwords
- [ ] Create external backup storage
- [ ] Train staff on recovery procedures
- [ ] Conduct first test restore
- [ ] Document all backup procedures
- [ ] Set calendar reminders for monthly restore drills
- [ ] Establish monitoring alerts for backup failures

---

## Contact & Escalation

**Primary Admin**: System Administrator
**Backup Owner**: Database Administrator
**Escalation**: IT Manager (for critical failures)

---

*Last Updated: March 16, 2026*
*Next Review: June 16, 2026*
