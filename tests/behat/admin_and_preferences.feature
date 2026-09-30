@tool @tool_teacherscaffold
Feature: Admins manage the stages and guided teachers, and teachers can turn guidance back on
  In order to adapt Teacher scaffold to my site
  As an admin
  I can edit the stages and move teachers between them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tina      | Guided   | teacher1@example.com |
    And the following "tool_teacherscaffold > tracked teachers" exist:
      | user     |
      | teacher1 |

  Scenario: The settings page previews the stages and saves new ones
    Given I log in as "admin"
    And I navigate to "Courses > Teacher scaffold > Teacher scaffold settings" in site administration
    Then I should see "Stage 1: File, Page, Forum, Text and media area"
    And I should see "Final stage: everything else"
    When I set the field "Stages" to multiline:
      """
      page, forum
      quiz, nosuchactivity
      """
    And I press "Save changes"
    Then I should see "This is not an installed activity: nosuchactivity"
    When I set the field "Stages" to multiline:
      """
      page, forum
      quiz
      """
    And I press "Save changes"
    Then I should see "Changes saved"
    And I should see "Stage 2: Quiz"

  Scenario: An admin unlocks the next stage for a teacher from the report
    Given I log in as "admin"
    And I navigate to "Courses > Teacher scaffold > Guided teachers" in site administration
    Then the following should exist in the "tool_teacherscaffold_report" table:
      | First name / Last name | Stage  | Status |
      | Tina Guided            | 1 of 4 | Guided |
    When I click on "Unlock next stage" "link" in the "Tina Guided" "table_row"
    And I press "Continue"
    Then I should see "The next stage has been unlocked for Tina Guided."
    And the following should exist in the "tool_teacherscaffold_report" table:
      | First name / Last name | Stage  |
      | Tina Guided            | 2 of 4 |

  Scenario: A teacher who chose to see everything turns the list back on from Preferences
    Given I log in as "teacher1"
    And I visit "/admin/tool/teacherscaffold/optout.php"
    And I press "Continue"
    And I follow "Preferences" in the user menu
    When I follow "Step-by-step activity list"
    Then I should see "The step-by-step list is off."
    When I press "Turn the step-by-step list back on"
    Then I should see "The step-by-step list is back on. You are at stage 1."
    And I should see "You can add: File, Page, Forum, Text and media area."

  Scenario: Teacher names are shown as text, not markup, in report messages
    Given the following "users" exist:
      | username | firstname      | lastname | email                |
      | teacher2 | Theo &amp;     | Markup   | teacher2@example.com |
    And the following "tool_teacherscaffold > tracked teachers" exist:
      | user     |
      | teacher2 |
    And I log in as "admin"
    And I navigate to "Courses > Teacher scaffold > Guided teachers" in site administration
    When I click on "Unlock next stage" "link" in the "Markup" "table_row"
    And I press "Continue"
    Then I should see "The next stage has been unlocked for Theo &amp; Markup."
