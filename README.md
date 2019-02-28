# Capistrano PHP Test Project deployment 

Steps to create a php app deployment test environment:
## 1) Create a virtual machine
+ **1.1)** Create a folder to hold the vagrant machine and `cd` to it
+ **1.2)** In command line init vagrant project with a jessie 32-bits box:

      vagrant init diku/jessie32

+ **1.3)** Edit generated `Vagrantfile` to include a private network:

      Vagrant.configure("2") do |config|
        config.vm.box = "jessie32"
        config.vm.network "private_network", ip: "192.168.33.100"
      end
	  
+ **1.4)** Create ssh specific keys to connect with this machine with the `-f` option. This will genereate two files inside vagrant project named with -f option argument. One of this with .pub file extension (this is the public key):
    
      $ ssh-keygen -f php-jessie
      
+ **1.5)** Enter the vagrant machine with `vagrant ssh` and do update/upgrade:

      $ sudo apt-get update
      $ sudo apt-get upgrade
      
+ **1.6)** Create a nonroot user in charge of deployment and php-fpm processes:

      # create user and group deploy
      # add /home/deploy deploy's user home folder
      $ adduser deploy 
      # put deploy user among sudoers group
      $ usermod -a -G sudo deploy
      
+ **1.7)** In vagrant machine allow password ssh authentication to use scp. Edit `/etc/ssh/sshd_config` file:

      PasswordAuthentication yes
      
+ **1.8)** In host machine copy public key file to vagrant machine deploy's home folder. The colon `:` at the end targets the home folder:
      
      $ scp php-jessie-pub deploy@192.168.33.100:
      
+ **1.9)** Back to the vagrant machine, ***logged as deploy*** and inside its home folder `/home/deploy`, create `authorized_keys` file and paste host's pub key inside of it:
    
      $ mkdir ~/.ssh
      $ touch ~/.ssh/authorized_keys
      $ cat ~/php-jessie.pub >> ~/.ssh/authorized_keys
      # now protect `.ssh` folder
      $ chown -R deploy:deploy ~/.ssh # ensure deploy is the owner
      # only deploy users or user of deploy group can access/view/modify .ssh folder
      $ chmod 700 ~/.ssh  
      $ chmod 600 ~/.ssh/authorized_keys
    
**Now the host machine has access to vagrant machine without password**

+ **1.10)** Now (in the host machine) configure hosts to access the vagrant machine. Find the host `.ssh` folder (in windows it is located at `%HOME%.ssh`) and change the config file. Put full path to host private key located at vagrant folder in `IndentifyFile` parameter. I made an optional jessie host just for learning, but the Host wit or vagrant's ip (192.168.33.100) is **mandatory for capistrano use later**:

      Host jessie
        HostName 192.168.33.100
        User deploy
        Port 22
        IdentityFile /path/to/php-jessie
        IdentitiesOnly yes
      Host 192.168.33.100
        HostName 192.168.33.100
        User deploy
        Port 22
        IdentityFile C:/Users/marcos.filho/Desktop/test/php-jessie
        IdentitiesOnly yes
        
Now we can access the virtal machine without password with these options:

      ssh jessie
      ssh deploy@192.168.33.100
      ssh 192.168.33.100
      
+ **1.11)** *[OPTIONAL]* Secure vagrant ssh access, disabling root login and passwords. With these setting in place broke `vagrant ssh` and other vagrant commands like `vagrant reload`.
      
      # changes in /etc/ssh/sshd_config
      PasswordAuthentication no
      PermitRootLogin no
      # restart sshd service in bash
      sudo service ssh restart
      
+ **1.12)**  **PHP INSTALLATION**. First we need to enable **PHP PPA** and after proceed  with PHP installation. We added the zip and unzip packages that composer will need for `--prefer-dist` packs. "Dist" packages permits local caching in the `/home/deploy/.composer` folder and this makes capistrano deploys faster since packages don't need to be reinstalled between deploys.
      
      # Enable PHP PPA on debian 8
      # bellow this package contains 'add-apt-repository' command that we will need
      $ sudo apt install software-properties-common
      # this enable connection with 'deb https://foo distro main' lines
      $ sudo apt install ca-certificates apt-transport-https 
      $ wget -q https://packages.sury.org/php/apt.gpg -O- | sudo apt-key add -
      $ sudo echo "deb https://packages.sury.org/php/ jessie main" | tee /etc/apt/sources.list.d/php.list

      # sudo apt update
      sudo apt install php7.2
      sudo apt install php7.2-cli php7.2-common php7.2-curl php7.2-gd php7.2-json php7.2-mbstring php7.2-mysql php7.2-xml php7.2-mcrypt
      sudo apt install php7.2-zip zip unzip 
      
+ **1.13)** Install PHP-FPM. First we add PHP ppa in the APT sources.list. 

      # this command actuacly replaces the previous commands of adding PHP ppa, except for apt-transport-https. I keep that for learning purposes and as an alternative
      $ sudo add-apt-repository ppa:ondrej/php
      $ sudo apt-get update
      $ sudo apt-get install php7.2-fpm
      
+ **1.14)** OPTIONAL: Adjust `/etc/php/7.2/fpm/php-fpm.conf`

      emergency_restart_threshold = 10
      emergency_restart_interval = 1m
      
Above setting when 10 PHP-FPM child processes fails within a interval of 1 minute the PHP-FPM master process is prompted ro gracefully restart.

+ **1.15)** PHP-FPM is a collection of related PHP child processes. Ideally one PHP app has its own PHP-FPM pool. In debian pool definitions are in `/etc/php/7.2/fpm/pool.d/*.conf` folder. PHP ships with a www pool and we will edit this pool. Edit `/etc/php/7.2/fpm/pool.d/www.conf` :

      [www] #change the name of the pool [optional]
      user = deploy
      group = deploy
      listen = 127.0.0.1:9000
      # if we create another pool give that pool another port
      # e.g. 127.0.0.1:9001
      listen.allowed_clients = 127.0.0.1 # only local connection
      pm.max_children = 5 # adjust according with machine's RAM
      
After save run `sudo service php7.2-fpm restart` to apply these.

+ **1.16)** INSTALL NGINX. `sudo apt install nginx`

+ **1.17)** Configure Virtual Host for applications. Create `apps` folder that holds each app and a `logs` folder that holds logs for all apps. The PHP app must live in a filesystem directory that is readable an writeable by the nonroot deploy user. Each app folder will hav a current folder that hold the app in production state.

      $ mkdir -p /home/deploy/apps/example.com/current/public
      $ mkdir -p /home/deploy/apps/logs
      $ chmod -R +rx /home/deploy
      
Nginx vhost will point to a public folder inside the current folder. In our example the nginx document root is `/home/deploy/apps/example.com/current/public`.
Now create another vhost configuration file at `/etc/nginx/sites-available/example.conf` with:

	  server {
		  listen 80;
		  server_name example.com;
		  index index.php;
		  client_max_body_size 50M;
		  error_log /home/deploy/apps/logs/example.error.log;
		  access_log /home/deploy/apps/logs/example.access.log;
		  root /home/deploy/apps/example.com/current/public;

		  location / {
			try_files $uri $uri/ /index.php$is_args$args;
		  }

		  location ~ \.php {
			try_files $uri =404;
			fastcgi_split_path_info ^(.+\.php)(/.+)$;
			include fastcgi_params;
			fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
			fastcgi_param SCRIPT_NAME $fastcgi_script_name;
			fastcgi_index index.php;
			fastcgi_pass 127.0.0.1:9000;
		  }
		  location = /status {
			access_log off;
			allow 127.0.0.1;
			allow 192.168.33.1;
			deny all;
			include fastcgi_params;
			fastcgi_param SCRIPT_NAME '/status';
			fastcgi_param SCRIPT_FILENAME '/status';
			fastcgi_pass 127.0.0.1:9000;
		  }
		  location = /ping {
			access_log off;
			allow 127.0.0.1;
			allow 192.168.33.1;
			deny all;
			include fastcgi_params;
			fastcgi_pass 127.0.0.1:9000;
	 	 }
	  }
    
*\status* and *\ping* locations are just for testing PHP-FPM. See these references bellow: 

[Nginx – Enable PHP-FPM Status Page](https://easyengine.io/tutorials/php/fpm-status-page) 

[Test PHP-FPM Ping Page](https://easyengine.io/tutorials/php/fpm-status-page/) 

The location `~ \.php{}` forwards HTTP requests to our previous PHP-FPM pool. When there is no response from PHP-FPM or the service is down Nginx respond with a **502 Bad Gateway** response.

+ **1.18)** Symlink the vhost of example.com app to the `sites-enabled` folder:

      $ sudo ln -s /etc/nginx/sites-available/example.conf \
      /etc/nginx/sites-enabled/example.conf
      
Then restart nginx service `sudo service nginx restart` 

+ **1.19)** In you machine's system (host machine) change the hosts file to include example.com host. In windows it is at `%WINDIR%\system32\drivers\etc\hosts` (%WINDIR% is a windows environment variable thats points to system main folder) and add local dns entry point to vagrant's ip:

      192.168.33.100 example.com
      
## 2) Capistrano install and setup
+ **2.1)** The server (vagrant machine) will need composer installed. For installation use the bash script offered by composer's official site: 

      $ wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet
      
You may replace the commit hash by whatever the last commit hash is on [master commit](https://github.com/composer/getcomposer.org/commits/master)
Or create a bash script with this content: 

		#!/bin/sh

		EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
		php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
		ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

		if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
		then
			>&2 echo 'ERROR: Invalid installer signature'
			rm composer-setup.php
			exit 1
		fi

		php composer-setup.php --quiet
		RESULT=$?
		rm composer-setup.php
		exit $RESULT 
    
If you named the script `composer_install.sh` whe have to give execution permissions and run it: 

      $ chmod +x composer_install.sh
      $ ./composer_install.sh   #this run the script
      
After execution in the folder of the script a `composer.phar` file appears. Run to make it global system wide available:

      $ mv composer.phar /usr/local/bin/composer
      
+ **2.2)** Install capistrano with `gem install capistrano` 

+ **2.3)** Make a PHP project managed by git and uploaded to github. I made simple example project with some composer dependencies. Capistrano will install only the **not dev** dependencies. The project is in this repo: 

[https://github.com/marcosmanto/deploy-test](https://github.com/marcosmanto/deploy-test) 

This project have a public folder where we put files that will be available for clients through nginx vhost.
The Capfile was generated with the `cap install` command. This command generates all scaffolding in the app: 

      Capfile
      config/
        deploy/
          production.rb
          staging.rb
        deploy.rb
      lib/
        capistrano/
          tasks/
          
+ **2.4)** Modify `config\deploy.rb` :

      lock "~> 3.11.0"
      set :application, "deploy_project"
      set :repo_url, "git@github.com:marcosmanto/deploy-test.git"
      set :deploy_to, "/home/deploy/apps/deploy_project"
      namespace :deploy do
        desc "Build"
        after :updated, :build do
        on roles(:web) do
        within release_path do
          execute :composer, "install --no-dev --prefer-dist"
          end
        end
        end
      end
      set :keep_releases, 3
      
Observe that the namespace block contains a hook that triggers composer install from dist after updated or first build.

+ **2.5)** Change the `config/deploy/production.rb` : 

      role :web, %w{deploy@192.168.33.100}
      
Here put the vagrant's machine ip. We need the connection without password (public key) setup done for this work and added a host to the ssh client config file. (**Step 1.10**)

+ **2.6)** Add the public key of vagrant machine to github in Settings > SSH and GPG Keys link 

+ **2.7)** Create a folder for deploy_project app 

      $ mkdir -p /home/deploy/apps/deploy_project/current
      $ chmod -R +rx /home/deploy/apps/deploy_project
      
+ **2.8)** Install git on the server with `sudo apt install git`

+ **2.9)** Create the vhost for deploy_project in `/etc/nginx/sites-available/deploy_project.conf` :

	  server {
		  listen 80;
		  server_name dev-project.com;
		  index index.php;
		  client_max_body_size 50M;
		  error_log /home/deploy/apps/logs/deploy_project.error.log;
		  access_log /home/deploy/apps/logs/deploy_project.access.log;
		  root /home/deploy/apps/deploy_project/current/public;
		  location / {    try_files $uri $uri/ /index.php$is_args$args;
		  }

		  location ~ \.php {
			try_files $uri =404;
			fastcgi_split_path_info ^(.+\.php)(/.+)$;
			include fastcgi_params;
			fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
			fastcgi_param SCRIPT_NAME $fastcgi_script_name;
			fastcgi_index index.php;
			fastcgi_pass 127.0.0.1:9000;
		  }
	  }
    
I will use the same pool for example.com site but the best practice would be to create a new pool on another port like 127.0.0.1:9001 and change the `fastcgi_pass` to this port on nginx specific project conf. 

+ **2.10)** To deploy, on the project root folder run `cap production deploy` 

+ **2.11)** PHP-FPM creates cache and sometimes after an deploy update the page stays the same. In that case run `sudo service php7.2 reload` 

## 3) Deploy Cycle 
+ **3.1)** After changes are made in the project, commit these changes locally first:

      $ git add .
      $ git commit -m 'Change commited'
+ **3.2)** Push those changes to github: 

      $ git push

+ **3.3)** Run `cap production deploy` again 




### [References]
#### PHP-FPM Cache
* [https://ma.ttias.be/how-to-clear-php-opcache/](https://ma.ttias.be/how-to-clear-php-opcache/) 
#### Composer cache
* [Topic 4 of this excelent article](https://moquet.net/blog/5-features-about-composer-php/) 

Loading composer components from cache requires zip and unzip linux libs and this make the deploy faster. 
#### SSH host configurations
[Simplify Your Life With an SSH Config File](https://nerderati.com/2011/03/17/simplify-your-life-with-an-ssh-config-file/)
#### Installing PHP on Debian 8
[How To Install PHP (7.2, 7.1 & 5.6) on Debian 8 Jessie](https://tecadmin.net/install-php7-on-debian/)
#### Error connecting to github via public key
[Solution for 'ssh: connect to host github.com port 22: Connection timed out' error](http://www.inanzzz.com/index.php/post/wa1f/solution-for-ssh-connect-to-host-github-com-port-22-connection-timed-out-error) 

Sometimes due to network changes we are unable to connect on defaul 22 port. But this article give us an alternative. 
