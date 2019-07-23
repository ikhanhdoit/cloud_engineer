# My Steps on setting up a cloud environment involving many services.

## 1. Creating basic account as root:

- Create an IAM user with admin policy to avoid using root when managing account.

- Set up MFA for your root user.

- Set up Billing Alerts for anything over a few dollars in CloudWatch.

- Configure the AWS CLI for your user. 
    - Used 'aws configure' in Linux terminal to set it up.
    - The AWS Access Key ID and AWS Secret Access Key is needed. It can be found on IAM user Security Credentials.

## 2. Web Hosting Basics:

- Deploy a EC2 Virtual Machine and host a simple static "Fortune-of-the-Day Coming Soon" web page.
    - Used Amazon Linux 2 AMI and t2.micro
    - Also created and/or set up VPC, Public/Private Subnets, associated Route Table, created/attached Internet Gateway, attached Elastic IP, NAT Gateway, Security Groups, NACL.
    - ** _Had issues connecting to private subnet via SSH through the NAT gateway in the public subnet since we cannot get to the private subnet directly without the keypair and the private subnet is not accessible from the internet._ **
        - Solved by saving keypair on NAT instance and then SSH to the private subnet from the NAT instance from the public subnet.
            - Without doing it this way, we cannot connect to instance on private subnet. We get this error "Permission denied (publickey,gssapi-keyex,gssapi-with-mic)."
        - Above solution was not the most secure. 
        - Another way to fix is to use 'ssh-keygen' in public subnet and NAT and then SSH into the private subnet.
            - Then vim into /etc/ssh/sshd_config file to change PasswordAuthentication to "yes" and sudo 'service sshd restart'.
            - Then set up 'sudo passwd ec2-user' to create the password and logout to the public subnet.
            - Next is to 'ssh-copy-id ec2-user@<ip address of subnet>' and enter password. Now you can SSH into the private subnet without a Key Pair. This eliminates having a key pair in your public subnet.
        - Other option is to configure the ssh-agent forwarding with something like PuTTY.
    - Created website using Apache by using "sudo yum install -y httpd" and start it by using "sudo service httpd start"
    - Used "sudo chkconfig httpd on" to make sure the web server starts at each system boot.
        - ** _"Forbidden. You don't have permission to access /index.html on this server." was shown. It was because /var/www/html/index.html file was not chmod 644 and it restricted public access. Change to 644 to correct._ **
    - ** NACL, Route Tables, and Security Groups can be configured to be more narrow to your infrastructure and IP Addresses instead of 0.0.0.0/0 and ALL ports. **

- Take a snapshot of your VM, delete the VM, and deploy a new one from the snapshot. Basically disk backup + disk restore.
    - Can create snapshot of EBS volume and AMI image of instance to create backup
    - Snapshots are incremental and useful for backups when done often or before major updates. EBS Snapshots are better for backups than AMIs because of scalability and consistency.
    - AMIs can be useful for instance replication and also backups. AMI does not scale well with large volumes.
    - My solution is to make EBS Snapshots and create/attach them to EC2 instances instead of creating AMI instances.
        - You can do this by reassigning the EBS Volume to the root volume, either sda or xvda.
        - Instances need to be stopped for root volumes to be detached.

- Checkpoint: You can view a simple HTML page served from your EC2 instance. Elastic IP can be used as well.

## 3. Auto Scaling

- Create an AMI from that VM and put it in an autoscaling group so one VM always exists.

- Put a Elastic Load Balancer infront of that VM and load balance between two Availability Zones (one EC2 in each AZ).

- Checkpoint: You can view a simple HTML page served from both of your EC2 instances. You can turn one off and your website is still accessible.
