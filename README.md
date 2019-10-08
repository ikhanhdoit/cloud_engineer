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
            - GRANT ALL PRIVILEGES ON \*.\* TO '[user]'@'[% or localhost]' WITH GRANT OPTION;
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
    - You need to change SELinux for RHEL distro from "enforcing" to "permissive". Edit the '/etc/selinux/config' file and change "SELINUX=enforcing" to "SELINUX=permissive".
        - Then reboot the system with 'sudo reboot' and in a minute or two your website can now query the database.

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
    - Docker needs to be installed before installing kubectl and minikube.
    - The Kubernetes official website documentation is an amazing source. Very detailed and interactive environments to learn and practice.
    - Minikube and 'sudo minikube start --vm-driver=none' was used as this is a dev environment.
        - The '--vm-driver=none' will run an insecure kubernetes apiserver as root that may leave the host vulnerable to attacks. This is okay for now since it is not prod.
    - In order to not keep putitng sudo for your commands, you can put 'sudo chown -R [username] .kube/ .minikube/'.
    - Using the repository, you can then deploy the containers with 'kubectl create -f web-deployment.yaml' and 'kubectl create -f db-deployment.yaml'.
        - These yaml files are in the GitHub Repository and they reference docker images in the Docker Hub repository.
        - This will build and run the db and web containers with replicas and port numbers.
    - In order to allow the outside internet to access the web container,  you can use 'kubectl create -f web-service.yaml' to create the service with NodePort enabled.
    - I then exposed the database to be reachable within the cluster and has an endpoint for the two replica db-deployment. Use 'kubectl expose deployment db --type=ClusterIP --name=[db service name]'.
        - The previous step with the yaml file could be done to make a service or you can expose the pod after the deployment is created.
        - There are 3 options for the '--type=[type of traffic to your cluster]'.
            - ClusterIP = Exposes pod to other pods within the cluster.
            - NodePort = Exposes pod to outside of the cluster through a NAT.
            - LoadBalancer = Creates an external load balancer that will forward traffic to your service.
    - Find out the database endpoint by using 'kubectl get service' and input this into each of the web pods with 'kubectl get pods'.
        - Unfortunately I had to go into each replica container and put in the database endpoint into the "query.php" and "insert.php" files like we did in the previous ways instead of automatically being filled in.
            - Currently unsure how to do this but feels like there is a way to do this through kubernetes.
        - You enter the container by either the previous way of 'docker exec -it [docker container id] /bin/bash' or through kubernetes with 'kubectl exec -it [pod name] /bin/bash'.
    - Unfortunately using minikube and limits yourself for only local dev environments and to play around with, not really for prod.
        - This shows since the default is not exposed to the internet and limited to a single node Kubernetes cluster.
        
- Manage and Deploy the same thing on AWS ECS.
    - Choose ECS in AWS and choose "Create Cluster" instead of "Get Started" to avoid using Fargate.
        - Nothing wrong with Fargate and it's easier to use for ECS but we will do it the long and hard way to understand what's happening.
        - Select "ECS Linux + Networking" and then Next.
        - Choose the Cluster name and for this project, I've chosen "t2.micro" for ECS instance type for lower cost (or free) and low resources needed.
        - Select "Amazon Linux 1 AMI" for the EC2 AMI ID. This was chosen due to the restrictions on Amazon Linux 2 AMI with the yum packages.
        - Select the Key Pair you want along with the VPC and Public Subnets you want. Also choose the Security group for this instance.
        - ecsInstanceRole for the container IAM role should already be defaulted to this field. This allows the instance to have ECS agent communicate to other AWS services.
        - Click "Create Cluster" and it will create the cluster through CloudFormation. You can view the steps in CloudFormation for the ASG, Launch Config, etc.
    - Next we will create new Task Definitions and choose EC2.
        - Select the Task Definition Name
        - Click add container and choose a container name. Also fill in the container image location (in my case, dockerhub).
            - I then created a soft limit of 300MiB for Memory Limits and on port 80 for both host and container port.
            - CPU units under Environment, I chose 200 and clicked on Essential. Also enabled Log configuration with CloudWatch Logs and then click "Add".
        - Click "Create" to complete the Task Definition.
    - Now we choose "Run Task" under "Actions" Tab to run the task.
        - Select EC2 for Launch type and then click "Run Task"
    - Now that the instance is running, you can ssh into it with the key pair and 'ssh -i <key_pair> ec2-user@<public_ip of instance>'.
        - Now that you are in the instance, you want to go into the docker container by 'docker exec -it <container_id> /bin/bash'.
        - Just like before, update the "insert.php" and "query.php" file with the RDS endpoint, username, and password with vim.
- You can also create a container Service for autoscaling or load balancing.
    - Under the Service tab, click "Create".
        - Select EC2 for the Launch Type and choose your Task Definition and correct cluster.
        - Choose a Service name and the number of tasks you want to have for your Service. Adjust your Minimum health percent as needed and click "next step".
        - Choose a load balancer if needed (usually Application Load Balancer) and then select "next step".
        - If you wish to had auto scaling, select "Configure Service Auto Scaling..." and choose the minimum number of tasks, desired tasks, and maxium number of tasks.
            - Select the Scaling policy type of either Target tracking or Step scaling. Select policy name and choose your metrics similar to EC2 Autoscaling and CloudWatch.
        - Select "next step" and "Create Service" if everything looks good.
    - You can now go into Route 53 and update the public IP to your website name if desired. After a few minutes, your website should work now.
- EKS was not used as the pricing for it is too much for this project. It is currently $144/month for each EKS cluster.

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
    - Had to learn Kubernetes, minikube, and how to create yaml files to deploy the deployments and services for the pods.
    - 'kubectl get pods', 'kubectl get deployments', 'kubectl get services', 'kubectl get nodes', 'minikube status', and 'kubectl describe [resource]' were all very important/basic commands.
    - Minikube dashboard is not working at the moment. 
    
## 6. Serverless

- Write a AWS Lambda function to email you a list of all of the Fortunes in the RDS table every night. Implement Least Privilege security for the Lambda Role. (Hint: Lambda using Python 3, Boto3, Amazon SES, scheduled with CloudWatch)
    - Select Lambda in AWS console and create a new function.
        - Choose a function name and use the Python 3.7 Runtime. For the Execution role, select to create a new role with basic Lambda permissions and create function.
    - 

- Refactor the above app into a Serverless app. This is where it get's a little more abstract and you'll have to do a lot of research, experimentation on your own.

- The architecture: Static S3 Website Front-End calls API Gateway which executes a Lambda Function which reads/updates data in the DyanmoDB table.

- Use your SSL enabled bucket as the primary domain landing page with static content.

- Create an AWS API Gateway, use it to forward HTTP requests to an AWS Lambda function that queries the same data from DynamoDB as your EB Microservice.

- Your S3 static content should make Javascript calls to the API Gateway and then update the page with the retrieved data.

- Once you have the "Get Fortune" API Gateway + Lambda working, do the "New Fortune" API.

- Checkpoint: Your API Gateway and S3 Bucket are fronted by CloudFront with SSL. You have no EC2 instances deployed. All work is done by AWS services and billed as consumed.

## 7. Automation

- These technologies are the most powerful when they're automated. You can make a Development environment in minutes and experiment and throw it away without a thought. This stuff isn't easy, but it's where the really skilled people excel.

- Automate the deployment of the architectures above. Use whatever tool you want. The popular ones are AWS CloudFormation or Teraform. Store your code in AWS CodeCommit or on GitHub. Yes, you can automate the deployment of ALL of the above with native AWS tools.

- I suggest when you get each app-related section of the done by hand you go back and automate the provisioning of the infrastructure. For example, automate the provisioning of your EC2 instance. Automate the creation of your S3 Bucket with Static Website Hosting enabled, etc. This is not easy, but it is very rewarding when you see it work.

## 8. Continuous Delivery

- As you become more familiar with Automating deployments you should explore and implement a Continuous Delivery pipeline.

- Develop a CI/CD pipeline to automatically update a dev deployment of your infrastructure when new code is published, and then build a workflow to update the production version if approved. Travis CI is a decent SaaS tool, Jenkins has a huge following too, if you want to stick with AWS-specific technologies you'll be looking at CodePipeline.
