---
key: recruitment
chapter: human-capital
order: 30
title: Recruitment
summary: Open job postings, follow candidates through selection and schedule interviews.
requires: [general.recruitment, general.recruitment.candidates, general.recruitment.jobs, general.recruitment.schedule, general.recruitment.settings]
updated: 2026-10-27
---
`Recruitment` is one hub with five tabs: `Dashboard`, `Selection Process`, `Schedule`, `Job Openings` and `Settings`. You see only the tabs your role allows.

## Who uses this

HR recruiters and interviewers who manage vacancies, candidates and interview calendars.

## Before you start

- Open `Human Capital` › `Recruitment`. The sidebar opens the first tab you may use.
- A job opening can be linked to a department, a position, a recruiter and the documents you request from applicants. Dropdown options come from `Settings`.
- Buttons such as `New Job Opening`, `Add Candidate` and `Schedule Interview` appear only if you have the Create permission on that tab.

## Step by step

### Create a job opening

1. Open `Job Openings` and select `New Job Opening`.
2. In `Posting`, enter `Job Title`, `Platform`, `Posting Link`, `Opening Date & Time` and `Closing Date & Time`, then `Employee Type`, `Location`, `Department`, `Position`, `Description` and `Requirements`.
3. Tick the `Documents Requested From Applicants`.
4. In `Internal Information`, set `Positions Needed`, the salary range and the `Recruiter`.
5. Save the form.

The list shows the status `Scheduled`, `Open` or `Closed`. It follows the opening and closing dates by itself. Use `Close now` to end a posting early; use `View`, `Edit` or `Delete` as needed.

### Add a candidate

1. Open `Selection Process` and select `Add Candidate`.
2. Enter the `Candidate Name`, `Position`, optional `Job Opening` and `Source`, plus email and phone.
3. Save. The candidate starts the selection steps.

Open a candidate to see `Candidate Details`, upload `Documents`, add internal notes and read the `Selection Timeline`.

### Move a candidate through selection

1. Open the candidate and change `Status`: `HR Interview`, `User Interview`, `Technical Test`, `Offer` or `Rejected`.
2. For an interview status, schedule the interview so it appears on the calendar.
3. Choose `Offer` when the candidate passes. The candidate can then be picked when writing an offering letter, see [[offering-letter]].

`Hired` is set automatically when the candidate accepts the offer and the employee account is created.

### Schedule an interview

1. Open `Schedule` and select `Schedule Interview`.
2. Choose the candidate, `Stage`, `Date`, `Start Time`, `End Time`, interviewers and an optional `Meeting Link`.
3. Save. Use the day, week or month view to read the calendar. An interview can be rescheduled, canceled or synced from its card.

### Set up the dropdown lists and calendar

`Settings` holds the `Calendar Source` (this system or a Microsoft account), the timezone, and the `Dropdown Lists` such as platforms and sources. Type a new option and press Enter to add it.

## What happens next

Candidates at the `Offer` stage are offered in `Offering Letter`. When they accept, the employee record is created and Onboarding begins, see [[onboarding]].

## Common questions

**Why can't I find a tab?** Tabs follow your role. Ask your administrator.

**Who sees internal notes?** Only the recruitment team.

## Related topics

- [[offering-letter]]
- [[onboarding]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/recruitment/index.blade.php, resources/views/hr-general/recruitment/jobs.blade.php, resources/views/hr-general/recruitment/job-form.blade.php, resources/views/hr-general/recruitment/candidates.blade.php, resources/views/hr-general/recruitment/candidate-show.blade.php, resources/views/hr-general/recruitment/schedule.blade.php, resources/views/hr-general/recruitment/settings.blade.php, app/Models/Recruitment/Candidate.php -->
