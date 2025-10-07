deploy:
	sudo chmod -R +x scripts
	./scripts/index.sh

backup:
	@echo "📦 Backing up MySQL database..."
	docker compose exec mysql sh -c 'exec mysqldump -u$${DB_USERNAME} -p$${DB_PASSWORD} $${DB_DATABASE}' > /home/quickfund/backup_`date +%F_%H-%M-%S`.sql
	@echo "✅ Backup complete! File saved in /home/quickfund/"