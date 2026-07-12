# US-SB-1-1 · View Available Subscriptions

**Epic:** Subscriptions
**Actor:** User (Student)

## Story

**As a** user,
**I want to** view all available subscription plans,
**So that** I can compare them and choose the one that best fits my needs.

## Flow

- 🎓 **User** → Opens the **Subscriptions** page
- ⚙️ **System** → Displays all active subscription plans.

For each subscription, the system displays:

- Subscription name
- Description
- Price
- Included courses
  - For each included course:
    - Lesson access type (All Lessons / Specific Lessons)
    - Number of accessible lessons
- Course access type (All Courses / Specific Courses)
- Expiration type
  - Duration in Days
  - Specific Expiration Date

- If the expiration type is **Duration in Days**:
  - ⚙️ **System** → Displays the subscription duration (e.g., 30 Days).
- If the expiration type is **Specific Expiration Date**:
  - ⚙️ **System** → Displays the fixed expiration date.

- 🎓 **User** → Opens a subscription to view its details.
- ⚙️ **System** → Displays:
  - Subscription information
  - Included courses
    - For each included course:
      - Lesson access type
      - Accessible lessons
  - Price
  - Expiration information

**If the user already has an active subscription:**

- ⚙️ **System** → Displays the active subscription.
- ⚙️ **System** → Disables purchasing another subscription.

## Business Rules

- Only active subscriptions are displayed.
- Inactive subscriptions are hidden.
- Users can view subscription details without purchasing.
- A user can have only one active subscription at a time.
- Users with an active subscription cannot purchase another subscription until the current one expires or is manually removed by an admin.
