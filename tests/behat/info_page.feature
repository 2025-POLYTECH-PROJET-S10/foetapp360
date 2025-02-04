@mod @mod_hippotrack
Feature: Display of information before starting a hippotrack
  As a student
  In order to start a hippotrack with confidence
  I need information about the hippotrack settings before I start an attempt

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Student   | One      | student@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student  | C1     | student |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name  | questiontext               |
      | Test questions   | truefalse   | TF1   | Text of the first question |

  Scenario: Check the pass grade is displayed
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | hippotrack       | HippoTrack 1 | HippoTrack 1 description | C1     | hippotrack1    | 60.00     |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "HippoTrack 1" "mod_hippotrack > View" page logged in as "student"
    Then I should see "Grade to pass: 60.00 out of 100.00"

  Scenario: Check the pass grade is displayed with custom decimal separator
    Given the following "language customisations" exist:
      | component       | stringid | value |
      | core_langconfig | decsep   | #     |
    And the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | hippotrack       | HippoTrack 1 | HippoTrack 1 description | C1     | hippotrack1    | 60#00     |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "HippoTrack 1" "mod_hippotrack > View" page logged in as "student"
    Then I should see "Grade to pass: 60#00 out of 100#00"

  Scenario: Check the pass grade is not displayed if not set
    Given the following "activities" exist:
      | activity   | name   | intro              | course | idnumber | gradepass |
      | hippotrack       | HippoTrack 1 | HippoTrack 1 description | C1     | hippotrack1    |           |
    And hippotrack "HippoTrack 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    When I am on the "HippoTrack 1" "mod_hippotrack > View" page logged in as "student"
    Then I should not see "Grade to pass: 0.00"
