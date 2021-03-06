@mod @mod_reactforum
Feature: Students can edit or delete their reactforum posts within a set time limit
  In order to refine reactforum posts
  As a user
  I need to edit or delete my reactforum posts within a certain period of time after posting

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity   | name                   | intro                   | course  | idnumber  |
      | reactforum      | Test reactforum name        | Test reactforum description  | C1      | reactforum     |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I add a new discussion to "Test reactforum name" reactforum with:
      | Subject | ReactForum post subject |
      | Message | This is the body |

  Scenario: Edit reactforum post
    Given I follow "ReactForum post subject"
    And I follow "Edit"
    When I set the following fields to these values:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"

  Scenario: Delete reactforum post
    Given I follow "ReactForum post subject"
    When I follow "Delete"
    And I press "Continue"
    Then I should not see "ReactForum post subject"

  @javascript @block_recent_activity
  Scenario: Time limit expires
    Given I log out
    And I log in as "admin"
    And I navigate to "Security > Site security settings" in site administration
    And I set the field "Maximum time to edit posts" to "1 minutes"
    And I press "Save changes"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Recent activity" block
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "New reactforum posts:" in the "Recent activity" "block"
    And I should see "ReactForum post subject" in the "Recent activity" "block"
    When I wait "61" seconds
    And I follow "ReactForum post subject"
    Then I should not see "Edit" in the "region-main" "region"
    And I should not see "Delete" in the "region-main" "region"
