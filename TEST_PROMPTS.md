# Test Prompts for Recent Fixes

## Test 12-hour datetime format in responses:

1. **Create an event called "Format Test" on January 5th at 2:30 pm**

2. **What are the start and end times for "Format Test"?**
   - Should show "2:30 pm" not "14:30"

## Test end time update (preserves start time):

3. **Create an event called "End Time Test" on January 6th at 9am**

4. **Set the end time of "End Time Test" to 2pm**
   - Should preserve 9am start

5. **What are the start and end times for "End Time Test"?**
   - Should show: start 9am, end 2pm - NOT start 2pm

## Test time-only update with seconds format:

6. **Update the end time of "End Time Test" to 7:30 pm**
   - Tests that "7:30 pm" or "19:30:00" formats work correctly

7. **What are the start and end times for "End Time Test"?**
   - Should show: start 9am, end 7:30pm - start should still be preserved

## What These Prompts Verify:

- ✅ 12-hour format in responses (fixed by changing format string to `'F j, Y, g:i a'`)
- ✅ Start time preservation when only end time is updated (fixed by converting GMT to local before merging)
- ✅ Time-only updates with seconds format (fixed by stripping seconds before parsing)

