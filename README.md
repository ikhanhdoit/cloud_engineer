# My Steps on setting up a cloud environment involving many services.

## 1. Creating basic account as root:

- Create an IAM user with admin policy to avoid using root when managing account.

- Set up MFA for your root user.

- Set up Billing Alerts for anything over a few dollars in CloudWatch.

- Configure the AWS CLI for your user. 
    - Used 'aws configure' in Linux terminal to set it up.
    - The AWS Access Key ID and AWS Secret Access Key is needed. It can be found on IAM user Security Credentials.
    
- Issues:
    - New UI for CloudWatch Billing Alerts. Had to redo and add another email to SNS to receive alert email.

## 2. Web Hosting Basics:

- Deploy a EC2 Virtual Machine and host a simple static "Fortune-of-the-Day Coming Soon" web page.
    - Used Amazon Linux 2 AMI and t2.micro
    - Also created and/or set up VPC, Public/Private Subnets, associated Route Table, created/attached Internet Gateway, attached Elastic IP, NAT Gateway, Security Groups, NACL.
    - ** _Had issues connecting to private subnet via SSH through the NAT gateway in the public subnet since we cannot get to the private subnet directly without the keypair and the private subnet is not accessible from the internet._ **
        - Solved by saving keypair on NAT instance and then SSH to the private subnet from the NAT instance from the public subnet.
            - Without doing it this way, we cannot connect to instance on private subnet. We get this error "Permission denied (publickey,gssapi-keyex,gssapi-with-mic)."
        - Above solution was not the most secure. 
        - Another way to fix is to use 'ssh-keygen' in public subnet and NAT and then SSH into the private subnet.
            - Then vim into /etc/ssh/sshd_config file to change PasswordAuthentication to "yes" and 'sudo service sshd restart'.
            - Then set up 'sudo passwd ec2-user' to create the password and logout to the public subnet.
            - Next is to 'ssh-copy-id ec2-user@[ip address of subnet]' in the public subnet and enter the password. Now you can SSH into the private subnet without a Key Pair. This eliminates having a key pair in your public subnet.
            ** _When creating a new instance, the default setting makes PasswordAuthentication "no." Go to '/etc/cloud/cloud.cfg' and change "ssh_pwauth" to "true"
            - This could also all be done with a script in user-data upon launch.
        - Other option is to configure the ssh-agent forwarding with something like PuTTY.
    - Created website using Apache by using "sudo yum install -y httpd" and start it by using "sudo service httpd start"
    - Used "sudo chkconfig httpd on" to make sure the web server starts at each system boot.
        - ** _"Forbidden. You don't have permission to access /index.html on this server." was shown. It was because /var/www/html/index.html file was not chmod 644 and it restricted public access. Change to 644 to correct._ **
    - ** NACL, Route Tables, and Security Groups can be configured to be more narrow to your infrastructure and IP Addresses instead of 0.0.0.0/0 and ALL ports. **
    
- ** _Alterative to using Amazon Linux 2. I used Red Hat Enterprise Linux and used the following user data script upon start of the instance:_**

    >see "user_data.sh" in the repository

- Take a snapshot of your VM, delete the VM, and deploy a new one from the snapshot. Basically disk backup + disk restore.
    - Can create snapshot of EBS volume and AMI image of instance to create backup
    - Snapshots are incremental and useful for backups when done often or before major updates. EBS Snapshots are better for backups than AMIs because of scalability and consistency.
    - AMIs can be useful for instance replication and also backups. AMI does not scale well with large volumes.
    - Best solution is to make EBS Snapshots and create/attach them to EC2 instances instead of creating AMI instances.
        - You can do this by reassigning the EBS Volume to the root volume, either sda or xvda.
        - Instances need to be stopped for root volumes to be detached.
    - For the purpose of this project, I will just create an AMI of the VM.

- Checkpoint: You can view a simple HTML page served from your EC2 instance. Elastic IP can be used as well.

- Issues:
    - Amazon Linux 2 has limited packages to install or not so easy to find packages. Went with Red Hat Enterprise Linux (RHEL) instances to simplify things and to be more industry standard.
    - Have to be careful about AMI and EBS snapshots, as they are not exactly under the free tier so price may increase.

## 3. Auto Scaling

- Put a Elastic Load Balancer infront of that VM and load balance between two Availability Zones (one EC2 in each AZ).
    - Application Load Balancer was created with a target group.

- Create an AMI from that VM and put it in an autoscaling group so one VM always exists.
    - A Launch Configuration must be done with include the AMI. 
        - An important note is to choose "Assign a public IP address to every instance"
    - For Auto Scaling Group, choose the Launch Configuration you just created.
    - Choose your group size and click on "Advanced" in order to choose Load Balancer.
    - Then choose "Keep this group at its initial size" for the group size. You can use scaling policies if you plan to scale your application up and down.
        - For now we will use the ASG to ensure 1 instance is running at all times.

- Checkpoint: You can view a simple HTML page served from both of your EC2 instances. You can turn one off and your website is still accessible.
    - You can test your Auto Scaling Group by terminating the instance and see if another instance creates itself.
    - Be aware of EBS volumes if you didn't set it to delete on termination. This could rack up unncessary costs.
    - Auto Scaling Group may take some time before automatically creating a new instance due to health check timers.
    
- Issues:
    - If there you do not delete EBS volumes upon termination, EBS volumes can build up when autoscaling and increase costs.
    - Lots of changes with user-data information and Launch Configurations as new information and knowledge changes to initiate certain packages and software/configurations.

## 4. External Data

- Create a RDS MySQL table and experiment with loading and retrieving data manually, then do the same via a script on local machine.
    - RDS was created with two private subnets in the subnet group. Master username and password is created and needed to sign in.
    - Security group was created to only have port 3306 inbound for traffic for MySQL. Public instance's Security Group also updated to allow incoming traffic to IP of RDS.
        - Since RDS is in the private subnet and "Publicly Accessible" is "No," no internet access is available and cannot connect from local computer. Must be through the EC2 instance (or NAT) from public subnet.
    - Sign into RDS with from EC2 instance (web server) with the command 'mysql -h [RDS endpoint] -P 3306 -u [master username] -p' and then type in the password.
    - Use 'CREATE DATABASE [db name];', 'CREATE TABLE [table name] ([items included]);', and 'INSERT FROM [table name] VALUE([value from items included]);' to create your database tables.
        - 'SELECT * FROM [table name];', 'USE [database name];', and 'DESCRIBE [table name];' were common commands used in MySQL CLI.
        - In order to create user, type:
            - CREATE USER '[user]'@'[% or localhost]' IDENTIFIED BY '[enter_password]';
            - GRANT PRIVILEGES ON \*.\* TO '[user]'@'[% or localhost]' WITH GRANT OPTION;
            - FLUSH PRIVILEGES;
    - fortune_script.sql script was also created for this database (see fortune_script.sql in the repository). 'SOURCE [file destination];' when inside the database or 'mysql -h [RDS endpoint] -u [username] -p [database name] < [script.sql]' when outside of database where you were before you connect to the database.
        - Assuming the script is not saved yet, you would need to 'sudo yum install wget' and then 'wget [script location]' before running the SQL script.
            I used got it from my GitHub Repository. You can add this step to your user_data.sh script for EC2 if you want.
    - 'SELECT [table_name]', 'FROM [information_schema.tables]', 'WHERE [table_schema = @schema]'; are all useful commands.

- Refactor your static page into your Fortune-of-the-Day website (Node, PHP, Python, whatever) which reads/updates a list of fortunes in the AWS RDS table. (Hint: EC2 Instance Role)
    - Created IAM Role to grant access for EC2 instance to RDS.
    - Went to '/etc/httpd/conf/httpd.conf' to change "DirectoryIndex" to include index.php since the scripting language is PHP. If not then index.html would be the default.
    - Created 'index.php' file in '/var/www/html/' folder where httpd would default to read the file.
    - The file 'index.php' with the database query script is on the github folder allowing the website to be dynamic.
    - PHP Script to query database (query.php) and to insert new fortunes into the database (insert.php) is saved in the repository.

- Checkpoint: Your HA/AutoScaled website can now load/save data to a database between users and sessions

- Issues:
    - Had to learn SQL language and how databases work. This took quite some time as I am not familiar with it.
    - Had to learn how webservers interact and query from databases, specially MySQL. Also how to connect with private subnet and not public facing.
    - SELinux didn't allow the webserver to query from the MySQL database. I had to change the SELinux default from "enforcing" to "permissive." This is only an RHEL issue.
    - PHP language was a struggle. I tried Python (since I'm more familiar with it) but not as easy with webservers like PHP unless I learn Flask or Django. Tried to keep it as simple as possible for now.
        - Took a long time to get the PHP script to print out the query of the database. Needed to iterate the database and "print" to complete this task.
        - Think things might be easier for people to use a language they are familiar with, even though PHP is very popular for websites.
    - Originally was only able to query the database, but was then able to insert into the database with new fortunes on the website, which would then refresh itself to the original home page.
    - Because RDS creates snapshots, I removed the backup by changing the backup schedule to 0 days. Also removed storage autoscaling and monitoring to reduce costs at this time.

## 5. Microservices

- Retire that simple website and re-deploy it on Docker.
    - Using all of the previous scripts, I was able to slightly adjust them to make it work with Docker.
        - For example, user_data.sh was revamped into a Dockerfile for the web server. "index.php", "insert.php", "query.php" were all copied over to the Docker image with Dockerfile.
    - First created the database (db) Dockerfile. Was able to save the fortune_script.sql by 'COPY fortune_script.sql /docker-entrypoint-initdb.d/' instead of having to input another command to run the MySQL script.
        - By doing this, the script executes upon starting the container.
        - Also created the MySQL credentials by using ENV to set the MySQL root password, user, and user password.
            - Although the $username in "insert.php" and "query.php" is root. It did not work when using 'www' as the username.
    - Then created the webserver (web) Dockerfile. Made sure all of the important files were overwritten or added with COPY. This includes the httpd.conf file to allow index.php.
        - Also include "insert.php", "query.php", and "index.php". I included these in the '/var/www/html/' folder.
        - Be sure to 'EXPOSE 3306' for the MySQL database in the Dockerfile.
        - You also need to include 'CMD ["usr/sbin/httpd", "-DFOREGROUND"]' or else the container will stop.
    - Next is to use 'docker build -t <image_tag> .' when in the same folder as the Dockerfile you want to use to create the Docker image.
        - I used "db" as the tag name for my database and "web" as the tag name for the webserver.
    - After the images are created, you can now use 'docker run -d --name <container_tag> <image_name>'.
        - Trying to run 'docker run' with '-i' (interactive) or '-t' (TTY) instead of '-d' (detached) caused the container to hang. You can run 'docker exec -it <container_name> /bin/bash' afterwards to get into the container.
    - Although you can connect the container together during 'docker run', I connected them on the same docker network after the containers were already running.
        - Run 'docker network create <network_name>' to create a network name of your choice. A bridge driver will be default, which is what you want.
        - You can now connect to two containers to the same network by using 'docker network connect <network_name> <container_ID_or_name>' for each of the two containers.
    - You now need to find the docker IP address for the db to include in the "insert.php" and "query.php" where $servername is. Port number 3306 is optional.
        - Use 'docker container inspect <container_ID_or_name>' for the "db" container to find the "IPAddress".
    - To access the working page in a browser, use 'docker container inspect <container_ID_or_name>' for the "web" container to find the "IPAddress".
        - You should now see the website just the same as the simple website.
        - ** _Would need to use port forwarding in order to make it accessible to the internet._ **

- Use Docker-compose to run multi containers.
    - See "docker-compose.yml" file to see how to maintain multiple Docker containers together.
        - In this case, we do not need to make a docker-compose.yml file from scratch since we already have the Dockerfiles we previously created.
    - Network is defaulted as connected together so 'docker network create <network_name>' was not needed.
    - "build" in the docker-compose.yml file only needed the "context" (directory) of the Dockerfile and the "dockerfile" (name) of the Dockerfile.
    - 'docker-compose up' was done to build images and start the containers.

- Manage and Deploy the same thing on Kubernetes.
    - This was done instead of Docker Swarm.

- Manage and Deploy the same thing on ECS/EKS.

- Issues:
    - Having to figure out which database endpoint to use for index.php. Had to use the container IP address.
    - Creating the Dockerfiles, especially when it comes to CMD and making sure the image is build correctly.
    - Knowing when to use -d (detached) mode vs. -i (interactive) mode when using 'docker run' was crucial as some images did not run properly the correct mode was not used.
    - A lot of research and being stuck on this section as it was tough to figure out the nuances of how to deploy your application with Docker commands and Dockerfile.
    - Currently unable to automatically input the MySQL $servername/IP Address from the "db" container to the \*.php scripts in the "web" container.
        - Static IP could possibly be used but not best practice for security reasons.
    - Docker and containers aren't the best to use for databases as they are ephemeral.
    - 'kubectl get <resource>' did not work when relogging into the cloud server as the public IP changes each time (Linux Academy Playground).
        - Need to restart Docker by using 'systemctl restart docker' before it works again.
    - Minikube and NodePort are not to be used in prod, only local and dev environments.
    - Because containers are stateless and ephemeral, databases should not generally be used in this way. 
