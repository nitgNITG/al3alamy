# US-AD-2-3 · View Subscription Report

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** view a complete subscription report,
**So that** I can track subscribers, subscription changes, usage, offline payments, refunds, and subscription earnings.

## Flow

- 🔧 **Admin** → Opens **Subscription Reports**
- ⚙️ **System** → Displays subscription statistics and subscription records

### Subscription Summary

⚙️ **System** → Displays:

- Total subscriptions
- Active subscriptions
- Expired subscriptions
- Manually unsubscribed subscriptions
- Online subscriptions
- Manual offline subscriptions
- Total collected amount
- Total returned amount
- Net subscription earnings

### Subscriber Records

For each subscription record, the system displays:

- User name
- Subscription name
- Subscription source: **Online Purchase** / **Manual Assignment**
- Subscription status
- Subscription date and time
- Start date and time
- Expiration date and time
- Amount paid
- Payment method: **Online** / **Offline**
- Admin who manually assigned the subscription
- Admin who manually unsubscribed the user
- Unsubscribe reason
- Refund status
- Returned amount

### Subscription Data Snapshot

⚙️ **System** → Displays the subscription data that existed when the user originally subscribed:

- Subscription name
- Price at subscription time
- Amount paid
- Included courses
- Course access type
- Lesson access type for each course
- Included lessons
- Expiration type
- Duration days or specific expiration date
- Original start date
- Original calculated expiration date

### Current Subscription Data

⚙️ **System** → Displays the current subscription plan data after any admin updates:

- Current subscription name
- Current price
- Current included courses
- Current lesson access configuration
- Current included lessons
- Current expiration settings
- Current activation status

### Subscription Change History

⚙️ **System** → Displays every update made to the subscription plan, including:

- Course added
- Course removed
- Lesson added
- Lesson removed
- Lesson access changed from All Lessons to Specific Lessons
- Lesson access changed from Specific Lessons to All Lessons

The change history includes updates to:

- Subscription name
- Price
- Description
- Course access type
- Included courses
- Expiration type
- Duration days
- Specific expiration date
- Activation status

### User Subscription Update History

Because subscription updates affect users who already purchased the subscription, the system records:

- The affected user
- The update date and time
- The previous user subscription values
- The new user subscription values
- Courses added to the user's access
- Courses removed from the user's access
- Previous expiration date
- Recalculated expiration date

### Subscription Usage

For each subscriber, the system displays:

- Courses available through the subscription
- Lessons available through the subscription
- Lessons accessed
- Lessons not accessed
- Lessons completed
- Lessons currently in progress
- Last lesson accessed
- Subscription usage status

### Earnings

⚙️ **System** → Calculates:

- **Gross Subscription Earnings**
  - Online subscription payments
  - Manual offline subscription payments
- **Returned Amounts**
  - Full refunds
  - Partial refunds
- **Net Subscription Earnings** = Gross Subscription Earnings − Returned Amounts

- If an admin manually unsubscribes a user and selects **Price Returned**:
  - ⚙️ **System** → Deducts the returned amount from net subscription earnings
- If the admin selects **Price Not Returned**:
  - ⚙️ **System** → Keeps the amount in net subscription earnings

### Filters

🔧 **Admin** → Can filter the report by:

- User
- Subscription plan
- Subscription status
- Subscription source
- Payment method
- Payment receiver
- Assigned admin
- Receiving teacher
- Refund status
- Subscription date range
- Expiration date range
- Unsubscribe date range

### Export

- 🔧 **Admin** → Selects **Export**
- ⚙️ **System** → Exports the filtered report as: **CSV** / **PDF**

## Business Rules

- Subscription history must not change when the subscription plan is renamed or updated.
- The system must preserve both the original subscription snapshot and the current subscription data.
- Every subscription plan update must be recorded permanently.
- Every manual assignment and unsubscribe action must include the responsible admin.
- Refunds reduce earnings only by the actual amount returned.
- Course usage history remains available after expiration or manual unsubscribe.
- Deleted subscription plans must not remove historical subscriber or earnings records.
