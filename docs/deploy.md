# Deploying The Cisco Scripts

The scripts in /deploy are used to deploy the cisco scripts and required dependencies.
These Scripts will install all the dependencies as outlined in the system documentation.
In addition to this they will add git and gh cli to clone the repo and authenticate github.
For further developin you will need to install a code editor and setup the git user.name and user.email

## Installing on WSL (Ubuntu)
1. `wsl --install -d Ubuntu`
2. copy the deploy scripts into the container.
3. `chmod +x install.sh`
4. `./install.sh`
5. Execution will stop when you need to authenticate with github to pull the repo
  - Make sure your account has access to to the private repo
  - Login to github.com via https and start browser
  - The browser will fail, but a link and the device code will be provided, so you can log in on the host's browser
  - Once completed you should be prompted to press 'Enter' to continue execution
6. Once completed restart the wsl container.
  - `exit`
  - `wsl --shutdown`
  - `wsl -d Ubuntu`

## Deploy Scripts

### install.sh
This will ensure the other scripts are executable, and begin the install process by calling each of the other scripts.

### install_packages.sh
This installs all dependencies for the the cisco_scripts, php and apache server. It also installs git for getting the remote repo and gh to authenticate with github.

### clone_cisco_scripts.sh
This get sthe user to authenticate with github cli to github.com which hosts the remote repository. It will then clone the scripts to /usr/local/cisco_scripts.

### set_environemnt.sh
This will ensure all the files have the correct permissions:
- templates and network will be owned by www-data
- bin folder scripts will be given execute permissions 
- A System Environment Variable will be set to CISCO_PATH="/usr/local/cisco_scripts"

### setup_apache.sh
This will firstly symlink the var/www/html folder to the cisco_scripts/html folder
Next the apache2.conf will be symlinked.
Then the apache2 service will be started.




