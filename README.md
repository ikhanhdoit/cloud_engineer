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
            - GRANT PRIVILEGES ON *.* TO '[user]'@'[% or localhost]' WITH GRANT OPTION;
            - FLUSH PRIVILEGES;
    - fortune_script.sql script was also created for this database (see fortune_script.sql in the repository). 'SOURCE [file destination];' when inside the database or 'mysql -h [RDS endpoint] -u [username] -p [database name] < [script.sql]' when outside of database where you were before you connect to the database.
        - Assuming the script is not saved yet, you would need to 'sudo yum install wget' and then 'wget [script location]' before running the SQL script.
            I used got it from my GitHub Repository. You can add this step to your user_data bootstrap for EC2 if you want.
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
        
## 5. Web Hosting Platform-as-a-Service

- Retire that simple website and re-deploy it on Docker/Kubernetes

- Create a S3 Static Website Bucket, upload some sample static pages/files/images. Add those assets to your website.

- Register a domain (or re-use and existing one). Set Route53 as the Nameservers and use Route53 for DNS. Make www.yourdomain.com go to your Elastic Beanstalk. Make static.yourdomain.com serve data from the S3 bucket.

- Enable SSL for your Static S3 Website. This isn't exactly trivial. (Hint: CloudFront + ACM)

- Enable SSL for your Elastic Beanstalk Website.

- Checkpoint: Your HA/AutoScaled website now serves all data over HTTPS. The same as before, except you don't have to manage the servers, web server software, website deployment, or the load balancer.
