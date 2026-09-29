echo "Importing sabong_lara_db dump..."
sed -e 's/DEFINER=`[^`]*`@`[^`]*`//g' /init/db9.sql | mysql
mysql -e "GRANT ALL PRIVILEGES ON sabong_lara_db.* TO 'sabong_root'@'%'; FLUSH PRIVILEGES;"
echo "Database import complete."
