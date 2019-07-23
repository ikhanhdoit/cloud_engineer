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
    - ** Had issues connecting to private subnet via SSH through the NAT gateway in the public subnet since we cannot get to the private subnet directly without the keypair and the private subnet is not accessible from the internet.**
        - Solved by saving keypair on NAT instance and then SSH to the private subnet from the NAT instance from the public subnet.
        - Without doing it this way, we cannot connect to instance on private subnet. We get this error "Permission denied (publickey,gssapi-keyex,gssapi-with-mic)."
            Other option is to configure the ssh-agent forwarding with something like PuTTY.
    - Created website using Apache by using "sudo yum install -y httpd" and start it by using "sudo service httpd start"
    - Used "sudo chkconfig httpd on" to make sure the web server starts at each system boot.
        - ** "Forbidden. You don't have permission to access /index.html on this server." was shown. It was because /var/www/html/index.html file was not chmod 644 and it restricted public access. Change to 644 to correct.
    - ** NACL, Route Tables, and Security Groups can be configured to be more narrow to your infrastructure and IP Addresses instead of 0.0.0.0/0 and ALL ports. **

- Take a snapshot of your VM, delete the VM, and deploy a new one from the snapshot. Basically disk backup + disk restore.

- Checkpoint: You can view a simple HTML page served from your EC2 instance.
