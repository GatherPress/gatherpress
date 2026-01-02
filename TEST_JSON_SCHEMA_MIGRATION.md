# Testing JSON Schema Migration

All GatherPress abilities have been converted from flat format to JSON Schema format. This document outlines how to test that everything still works correctly.

## What Changed

1. **All abilities now use JSON Schema format** - `type: 'object'`, `properties: {...}`, `required: [...]`
2. **Removed flat format conversion** - No longer needed since all abilities use JSON Schema
3. **Simplified conversion code** - Just cleans up JSON Schema instead of converting formats

## Quick Test Commands

Run these AI prompts to test each ability category:

### 1. List Abilities (No Parameters)
- ✅ **"List all venues"** → Should list venues
- ✅ **"List all topics"** → Should list topics  
- ✅ **"List events"** → Should list events

### 2. List with Optional Parameters
- ✅ **"List events"** → Uses default max_number
- ✅ **"Show me 5 events"** → Should limit to 5
- ✅ **"Search for events about workshops"** → Should use search parameter

### 3. Create Abilities (Required + Optional Parameters)
- ✅ **"Create an event called 'Test Event' on December 15, 2025 at 7pm at Handcraft NYC"**
  - Required: `title`, `datetime_start` ✓
  - Optional: `venue_id` ✓
  
- ✅ **"Create a venue called Test Venue at 123 Main St, New York, NY"**
  - Required: `name`, `address` ✓
  - Optional: `phone`, `website` ✓

- ✅ **"Create a topic called 'Workshops'"**
  - Required: `name` ✓
  - Optional: `description`, `parent_id` ✓

### 4. Create Event with All Optional Fields
- ✅ **"Create an event called 'Full Event' on December 20, 2025 from 6pm to 8pm at [venue] with description 'This is a test' and topics [topic1] and [topic2] as draft"**
  - Tests: `datetime_end`, `description`, `topic_ids` (array), `post_status` ✓

### 5. Update Abilities
- ✅ **"Update venue [ID] to have address 456 Oak St"**
  - Required: `venue_id` ✓
  - Optional: `name`, `address`, `phone`, `website` ✓

- ✅ **"Update event [ID] to start at 8pm"**
  - Required: `event_id` ✓
  - Optional: all other fields ✓

### 6. Date Calculation
- ✅ **"Create events for the third Thursday of each month at [venue] called 'Monthly Meeting'"**
  - Should calculate dates correctly
  - Should use `ai/calculate-dates` if AI plugin active

### 7. Search & Batch Operations
- ✅ **"Search for events with 'workshop' in the title"**
  - Required: `search_term` ✓
  
- ✅ **"Change all 'Weekly Meeting' events to start at 7pm"**
  - Required: `search_term` ✓
  - Optional: `datetime_start`, `datetime_end`, `venue_id` ✓

## What to Check

### Success Indicators
- ✅ All abilities execute successfully
- ✅ Required fields are enforced (try creating event without title - should fail)
- ✅ Optional fields work (try creating event without description - should work)
- ✅ Array parameters work (topic_ids)
- ✅ No PHP errors in debug.log
- ✅ No OpenAI schema validation errors

### Common Issues to Watch For
- ❌ **Missing required fields** - If AI doesn't provide required fields, should see clear error
- ❌ **Schema validation errors** - OpenAI rejecting schemas (shouldn't happen now)
- ❌ **Array parameters** - `topic_ids` should work as array of integers

## Debug Commands

If something fails, check:

1. **PHP Error Log**:
   ```bash
   tail -20 /path/to/debug.log
   ```

2. **Check Ability Registration**:
   - Visit: `wp-admin` → Check that abilities are registered
   - Or use REST API: `/wp-json/abilities-api/v1/abilities`

3. **Verify Schema Format**:
   - Check debug log for schema conversion messages
   - Look for "Converting function" or "Cleaning schema" messages

## Test Checklist

Run through this checklist:

- [ ] List venues works
- [ ] List events works (with and without parameters)
- [ ] List topics works
- [ ] Create venue works (with required fields only)
- [ ] Create venue works (with all fields)
- [ ] Create topic works
- [ ] Create event works (minimal - title + datetime_start)
- [ ] Create event works (full - all fields including topic_ids array)
- [ ] Update venue works
- [ ] Update event works
- [ ] Search events works
- [ ] Update events batch works
- [ ] Calculate dates works (if AI plugin active, uses ai/calculate-dates)
- [ ] No PHP errors
- [ ] No OpenAI schema validation errors

## Expected Behavior

After conversion:
- ✅ All abilities work exactly as before
- ✅ Code is simpler (no flat format conversion)
- ✅ Schemas are properly validated by OpenAI
- ✅ All parameters work correctly (required, optional, arrays)

If all tests pass, the migration is successful! 🎉

