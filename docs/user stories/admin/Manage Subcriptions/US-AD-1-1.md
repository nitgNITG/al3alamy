# US-AD-1-1 · Create a Subscription Plan

**Epic:** Manage Subscriptions
**Actor:** Admin

## Story

**As an** admin,
**I want to** create a subscription plan and configure its available courses and expiration method,
**So that** users can purchase the subscription and access the appropriate courses for the configured period.

## Flow

### 1. Subscription Information

- 🔧 **Admin** → Opens subscription management
- 🔧 **Admin** → Selects **Create Subscription**
- 🔧 **Admin** → Enters the subscription name
- 🔧 **Admin** → Enters the subscription price
- 🔧 **Admin** → Adds an optional description

### 2. Included Courses and Lessons

- 🔧 **Admin** → Selects the course access type:
  - All Courses
  - Specific Courses

#### Option 1 — All Courses

- 🔧 **Admin** → Selects **All Courses**
- ⚙️ **System** → Includes all available courses and all lessons within those courses in the subscription.

#### Option 2 — Specific Courses

- 🔧 **Admin** → Selects **Specific Courses**
- 🔧 **Admin** → Selects one or more available courses.
- For each selected course:
  - 🔧 **Admin** → Selects the lesson access type:
    - All Lessons
    - Specific Lessons
  - If **All Lessons** is selected:
    - ⚙️ **System** → Includes all lessons within the selected course.
  - If **Specific Lessons** is selected:
    - 🔧 **Admin** → Selects one or more lessons from the selected course.
    - ⚙️ **System** → Includes only the selected lessons.

### 3. Expiration Settings

- 🔧 **Admin** → Selects the expiration type:
  - Duration in Days
  - Specific Expiration Date

#### Option 1 — Duration in Days

- 🔧 **Admin** → Selects **Duration in Days**
- 🔧 **Admin** → Enters the number of days
- ⚙️ **System** → Calculates the subscription expiration date starting from the user's successful subscription date and time

> **Example** — Duration: 5 days. User subscribes on August 1, 2026. Subscription expires 5 days after activation.

#### Option 2 — Specific Expiration Date

- 🔧 **Admin** → Selects **Specific Expiration Date**
- 🔧 **Admin** → Enters the expiration date
- ⚙️ **System** → Uses the same expiration date for every subscriber regardless of when they purchase the subscription

> **Example** — Expiration Date: August 8, 2026. All users lose access on August 8, 2026, regardless of their purchase date.

### 4. Activation

- 🔧 **Admin** → Sets the subscription status to **Active** or **Inactive**
- ⚙️ **System** → Validates the entered information
- ⚙️ **System** → Saves the subscription plan
- ⚙️ **System** → Displays the subscription to users if it is active

## Business Rules

### Subscription Rules

- Subscription name is required.
- Subscription price must be greater than or equal to zero.
- Only active subscriptions are available for purchase.

### Course and Lesson Rules

- The admin must choose one course access type.
- If **All Courses** is selected, the subscription grants access to all courses and all lessons.
- If **Specific Courses** is selected, at least one course must be selected.
- For every selected course, the admin must choose either **All Lessons** or **Specific Lessons**.
- If **Specific Lessons** is selected, at least one lesson must be selected.
- A lesson can only be selected from its own course.
- A course can belong to multiple subscriptions.
- A lesson can belong to multiple subscriptions.
- Duplicate courses or lessons are not allowed within the same subscription.

### Expiration Rules

- The admin must choose one expiration type.
- **Duration in Days** and **Specific Expiration Date** cannot be used together.
- **Duration in Days:**
  - Duration must be greater than zero.
  - The subscription starts after successful payment.
  - Each user's expiration date is calculated from their own subscription start date.
- **Specific Expiration Date:**
  - The expiration date must be a future date when the subscription is created.
  - All subscribers share the same expiration date.
  - Users who subscribe later receive access only until the configured expiration date.
  - The subscription cannot be purchased after the expiration date.

### Purchase Rules

- Subscription access starts only after successful payment.
- The system grants access only to the courses included in the subscription.
- When the subscription expires, the user loses access to all courses included in the subscription.
- A user cannot purchase another subscription while their current subscription is active.
