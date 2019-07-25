CREATE DATABASE fortune;
USE fortune;

CREATE TABLE fortunes (
 id INT NOT NULL primary key AUTO_INCREMENT,
 fortune VARCHAR(100) NOT NULL);

INSERT INTO fortunes (id, fortune)
 VALUES
 (NULL, "You will have to make a hard decision. Go with your first instincts."),
 (NULL, "Start saying 'Yes' more to when given the choice."),
 (NULL, "Don't dwell over the past too much. Start looking forward to tomorrow!"),
 (NULL, "Value those who appreciate you for who you are."),
 (NULL, "The first step to change is with YOU!"),
 (NULL, "Don't compare yourself to others. Be happy for your own accomplishments."),
 (NULL, "No one becomes successful over night. Remember everything is one step at a time.");