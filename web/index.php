<html>
<body>

<h1>Welcome to your Fortune of the Day!</h1>
<p>Here is your fortune:</p>

<?php include ('query.php'); ?>

<br/>
<br/>
<br/>
</body>

        <form action="insert.php" method="post">
                Or input your own fortune : <input type="text" name="fortune">
                <br/>
                <input type="submit" value="Insert">
        </form>

</html>
