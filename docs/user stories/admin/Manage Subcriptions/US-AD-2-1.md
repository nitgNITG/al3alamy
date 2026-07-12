# US-AD-2-1 · Assign a Subscription Manually

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** assign a specific subscription to a user manually,
**So that** users who paid an admin or teacher outside the platform can receive subscription access.

## Flow

- 🔧 **Admin** → Opens user management
- 🔧 **Admin** → Selects a user
- 🔧 **Admin** → Selects **Assign Subscription Manually**
- ⚙️ **System** → Checks whether the user already has an active subscription

**If the user has an active subscription:**

- ⚙️ **System** → Prevents assigning another subscription
- ⚙️ **System** → Displays that the user can have only one active subscription at a time

**If the user does not have an active subscription:**

- 🔧 **Admin** → Selects a subscription plan
- 🔧 **Admin** → Reviews the subscription details:
  - Subscription name
  - Current price
  - Included courses
  - Expiration type
  - Duration days or specific expiration date
- 🔧 **Admin** → Adds an optional note
- 🔧 **Admin** → Confirms the manual subscription
- ⚙️ **System** → Sets the subscription start date and time to the assignment date and time
- If the subscription uses **Duration in Days**:
  - ⚙️ **System** → Calculates the expiration date from the assignment date and time
- If the subscription uses **Specific Expiration Date**:
  - ⚙️ **System** → Uses the specific expiration date configured in the subscription plan
- ⚙️ **System** → Creates a snapshot of the subscription data at the time of assignment
- ⚙️ **System** → Activates the subscription for the user
- ⚙️ **System** → Grants access to the included courses
- ⚙️ **System** → Records the subscription as a manual offline subscription
- ⚙️ **System** → Records the admin who performed the action
- ⚙️ **System** → Adds the subscription amount to subscription earnings

## Subscription Snapshot

The system stores the subscription values that existed when it was assigned, including:

- Subscription name
- Price
- Amount received
- Included courses
- Course access type
- Lesson access type for each course
- Included lessons
- Expiration type
- Duration days or specific expiration date
- Start date
- Calculated expiration date

## Business Rules

- A user can have only one active subscription at a time.
- The subscription starts immediately when the admin confirms the assignment.
- The admin cannot select a different start date.
- The payment is recorded as an offline payment.
- The subscription follows the same access and expiration rules as an online subscription.
- Future updates to the subscription plan affect the manually subscribed user according to the subscription update rules.
- The original subscription snapshot remains available in reports even after the subscription plan is updated.
- The manual assignment appears in the user's subscription history and subscription reports.
