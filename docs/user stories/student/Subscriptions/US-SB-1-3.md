# US-SB-1-3 · View Subscriptions and Payment History

**Epic:** Subscriptions
**Actor:** User (Student)

## Story

**As a** user,
**I want to** view my subscriptions and payment history,
**So that** I can track my subscription status, payments, and access period.

## Flow

- 🎓 **User** → Opens **My Subscriptions**.
- ⚙️ **System** → Displays the current active subscription (if any).

For the active subscription, the system displays:

- Subscription name
- Status
- Start date
- Expiration date
- Price paid
- Included courses
  - For each course:
    - Lesson access type
    - Accessible lessons
- Remaining subscription period

- 🎓 **User** → Opens **Payment History**.
- ⚙️ **System** → Displays all subscription transactions.

For each transaction, the system displays:

- Subscription name
- Payment amount
- Payment date
- Payment method
- Payment status
- Subscription start date
- Subscription expiration date
- Subscription status

- 🎓 **User** → Opens a subscription record.
- ⚙️ **System** → Displays:
  - Subscription details
  - Included courses
  - Accessible lessons
  - Current subscription status
  - Course access status
  - Lesson access status

## Business Rules

- Users can view both active and previous subscriptions.
- Payment history cannot be modified by users.
- If a subscription expires, it remains visible in the history.
- If a subscription is manually removed by an admin, it remains visible in the user's history with its final status.
- Users can access only the lessons included in their active subscription.
- Users cannot access lessons that are not included in their subscription, even if the course itself is included.
