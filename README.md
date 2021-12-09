# nitroBackup
single file PHP backup script

This is a crude single file PHP script for managing a simple backup procedure on a Linux server with mysql databases.

•) Fill in the database connection parameter

•) Click 'Check connection'

•) Choose the DBs to be backed up

•) Choose the folders to include in the backup using shell wildcards, e.g.:

     /etc
     
     /home/user/*txt
     
•) In the field 'Where to store backup files', enter the path where the backup file will be created in the local machine

•) In 'Old files max age', enter the number of days to keep your backup files, e.g. if you enter 5 then any backup file older than 5 days will be deleted

•) In 'rsync to' enter the path to the machine that will store the backup file, e.g. server_name:/path/to/secure/location.

•) Click 'Save cfg': this will create an XML file named hostname.xml in the script folder containing your cfg data.

•) Click 'Create script' to write in the script folder the nitroBackup.sh


Make sure that your crontab user has ssh key access to the remote server, i.e. no asking passwords.

At this point you can add the nitroBackup.sh script to your crontab and forget about it.

Until that day when you need it, that is. 
