@mod @mod_hippotrack
Feature: Add a hippotrack
  In order to evaluate students
  As a teacher
  I need to create a hippotrack

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry1    | Teacher1 | teacher1@example.com |
      | student1 | Sam1      | Student1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activity" exists:
      | activity | hippotrack                  |
      | course   | C1                    |
      | idnumber | 00001                 |
      | name     | Test hippotrack name        |
      | intro    | Test hippotrack description |
      | section  | 1                     |
      | grade    | 10                    |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "True/False" question to the "Test hippotrack name" hippotrack with:
      | Question name                      | First question                          |
      | Question text                      | Answer the first question               |
      | General feedback                   | Thank you, this is the general feedback |
      | Correct answer                     | False                                   |
      | Feedback for the response 'True'.  | So you think it is true                 |
      | Feedback for the response 'False'. | So you think it is false                |
    And I log out

    And I am on the "Test hippotrack name" "hippotrack activity" page logged in as student1
    And I press "Attempt hippotrack"
    Then I should see "Question 1"
    And I should see "Answer the first question"
    And I set the field "True" to "1"
    And I press "Finish attempt ..."
    And I should see "Answer saved"
    And I press "Submit all and finish"

  @javascript @skip_chrome_zerosize
  Scenario: Add and configure small hippotrack and perform an attempt as a student with Javascript enabled
    Then I click on "Submit all and finish" "button" in the "Submit all your answers and finish?" "dialogue"
    And I should see "So you think it is true"
    And I should see "Thank you, this is the general feedback"
    And I should see "The correct answer is 'False'."
    And I follow "Finish review"
    And I should see "Highest grade: 0.00 / 10.00."

  Scenario: Add and configure small hippotrack and perform an attempt as a student with Javascript disabled
    Then I should see "So you think it is true"
    And I should see "Thank you, this is the general feedback"
    And I should see "The correct answer is 'False'."
    And I follow "Finish review"
    And I should see "Highest grade: 0.00 / 10.00."
