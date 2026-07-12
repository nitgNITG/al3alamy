# US-AD-1-2 · Update a Subscription Plan

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** update a subscription plan,
**So that** I can keep its information, available courses, and expiration settings up to date.

## Flow

### 1. Subscription Information

- 🔧 **Admin** → Opens subscription management
- 🔧 **Admin** → Selects a subscription
- 🔧 **Admin** → Selects **Update Subscription**
- 🔧 **Admin** → Updates one or more of the following:
  - Subscription name
  - Subscription price
  - Description

### 2. Included Courses and Lessons

- 🔧 **Admin** → Updates the course access type:
  - All Courses
  - Specific Courses
- If **Specific Courses** is selected:
  - 🔧 **Admin** → Adds or removes courses.
  - For each selected course:
    - 🔧 **Admin** → Updates the lesson access type (**All Lessons** / **Specific Lessons**).
    - If **Specific Lessons** is selected:
      - 🔧 **Admin** → Adds or removes lessons.
- ⚙️ **System** → Applies the changes to all users currently subscribed.

### 3. Expiration Settings

- 🔧 **Admin** → Updates the expiration type:
  - Duration in Days
  - Specific Expiration Date
- If **Duration in Days** is selected:
  - 🔧 **Admin** → Updates the number of days
- If **Specific Expiration Date** is selected:
  - 🔧 **Admin** → Updates the expiration date

### 4. Activation

- 🔧 **Admin** → Updates the subscription status if needed
- ⚙️ **System** → Validates the updated information
- ⚙️ **System** → Applies the changes to the subscription
- ⚙️ **System** → Updates all users who currently have this subscription

## Business Rules

- All validation rules from subscription creation apply.
- Updates affect both existing subscribers and future subscribers.
- If the included courses are changed, users immediately gain access to newly added courses and lose access to removed courses.
- If the expiration settings are changed, the system recalculates the expiration date for all active subscribers according to the updated configuration.
- If the subscription price is changed, the new price applies only to future purchases.
- A user can have only one active subscription at a time.
- If a course is added, subscribers immediately gain access according to its configured lesson access.
- If a course is removed, subscribers immediately lose access to all lessons in that course.
- If lessons are added, subscribers immediately gain access to them.
- If lessons are removed, subscribers immediately lose access to them.
- Existing lesson progress and completion history must be preserved even if access is removed.
- All other update rules remain unchanged.
