@tool @tool_teacherscaffold @javascript
Feature: Guided teachers start with a small activity chooser
  In order to learn Moodle a little at a time
  As a new teacher
  I see only the activities of my stage until I try them, and I can ask to see everything

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tina      | Guided   | teacher1@example.com |
      | teacher2 | Theo      | Control  | teacher2@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |

  Scenario: A stage 1 teacher sees only stage 1 activities, adds a forum and progress updates
    Given the following "tool_teacherscaffold > tracked teachers" exist:
      | user     |
      | teacher1 |
    And I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    Then I should see "Getting started: 0 of 4 tried in this stage."
    When I open the activity chooser
    Then I should see "Forum" in the "Add an activity or resource" "dialogue"
    And I should see "Page" in the "Add an activity or resource" "dialogue"
    And I should not see "Quiz" in the "Add an activity or resource" "dialogue"
    And I should not see "Assignment" in the "Add an activity or resource" "dialogue"
    And I click on "Close" "button" in the "Add an activity or resource" "dialogue"
    When I add a forum activity to course "Course 1" section "1" and I fill the form with:
      | Forum name | Questions |
    And I am on "Course 1" course homepage
    Then I should see "Getting started: 1 of 4 tried in this stage."

  Scenario: An untracked teacher in the same course sees every activity
    Given the following "tool_teacherscaffold > tracked teachers" exist:
      | user     |
      | teacher1 |
    And I am on the "C1" "Course" page logged in as "teacher2"
    And I turn editing mode on
    Then I should not see "Getting started"
    When I open the activity chooser
    Then I should see "Quiz" in the "Add an activity or resource" "dialogue"

  Scenario: Completing stage 1 unlocks stage 2
    Given the following "tool_teacherscaffold > tracked teachers" exist:
      | user     | used                  |
      | teacher1 | resource, page, label |
    # The congratulation is shown only when tool_wizards is not active.
    And the following config values are set as admin:
      | enabled | 0 | tool_wizards |
    And I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I should see "Getting started: 3 of 4 tried in this stage."
    When I add a forum activity to course "Course 1" section "1" and I fill the form with:
      | Forum name | Questions |
    Then I should see "Nice work! You've unlocked: URL, Folder, Assignment, Glossary."
    And I am on "Course 1" course homepage
    And I should see "Getting started: 0 of 4 tried in this stage."
    When I open the activity chooser
    Then I should see "Assignment" in the "Add an activity or resource" "dialogue"
    And I should not see "Quiz" in the "Add an activity or resource" "dialogue"

  Scenario: Show me everything gives the full chooser
    Given the following "tool_teacherscaffold > tracked teachers" exist:
      | user     |
      | teacher1 |
    And I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    When I click on "Show me everything" "link"
    And I press "Continue"
    Then I should see "You can now add every type of activity."
    And I should not see "Getting started"
    When I open the activity chooser
    Then I should see "Quiz" in the "Add an activity or resource" "dialogue"
