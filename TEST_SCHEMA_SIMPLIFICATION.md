# Testing Schema Simplification

This document outlines how to test that the simplified schema conversion logic works correctly after removing the flat format conversion.

## What Changed

1. **Kept** `convert_flat_schema_to_json()` method (still needed - GatherPress abilities use flat format)
2. **Simplified** `convert_input_schema_to_openai()` - detects format and handles both JSON Schema and flat
3. **Improved** `clean_json_schema()` to handle edge cases better

**Note:** GatherPress's own abilities still use flat format, while the AI plugin uses JSON Schema. The code handles both automatically.

## Test Plan

### 1. Test All Ability Types

Test each ability to ensure schemas are converted correctly and OpenAI accepts them:

#### Empty Schema Abilities
- **Test**: `gatherpress/list-venues` (no parameters)
- **Expected**: Should generate schema with empty `{}` properties
- **Command**: "List all venues"

#### Simple Schema Abilities  
- **Test**: `gatherpress/list-events` (one optional parameter)
- **Expected**: Should work correctly
- **Command**: "List events"

#### Complex Schema Abilities
- **Test**: `gatherpress/create-event` (multiple parameters, some required, array type)
- **Expected**: All parameters should be correct, `topic_ids` should have `items` property
- **Command**: "Create an event called 'Test Event' on December 1st at 7pm at [venue name]"

#### Date Calculation
- **Test**: `ai/calculate-dates` (if AI plugin active) or `gatherpress/calculate-dates`
- **Expected**: Pattern, occurrences, start_date should all work
- **Command**: "Create events for the third Thursday of each month at [venue] called Monthly Meeting"

### 2. Test Edge Cases

#### Empty Properties Object
- **Verify**: `list-venues` should encode `properties: {}` not `properties: []`
- **How**: Check that OpenAI accepts the schema (no validation errors)

#### Array Properties
- **Verify**: `topic_ids` in create-event should have `items: { type: 'integer' }`
- **How**: Try creating an event with topic IDs

#### Required vs Optional
- **Verify**: Required fields are in top-level `required` array, not in property definitions
- **How**: Try calling an ability without required fields - should get clear error

### 3. Functional Tests

Run these AI prompts to test end-to-end:

1. **"List all venues"**
   - Should list venues successfully
   - No schema errors

2. **"Create an event called 'Test' on December 15, 2025 at 7pm at [venue]"**
   - Should create event
   - Verify required fields work

3. **"Create events for every Tuesday at [venue] called 'Weekly Meeting'"**
   - Should calculate dates and create multiple events
   - Test date calculation ability

4. **"Create an event with topics [topic1] and [topic2]"**
   - Should handle array parameter correctly

### 4. Debug Logging

With `WP_DEBUG` and `WP_DEBUG_LOG` enabled, check for:

- **No errors** about invalid schemas
- **No warnings** about missing properties
- **No notices** about schema format issues

Look for these specific error patterns (should NOT appear):
- "Invalid schema for function"
- "array schema missing items"
- "False is not of type 'array'"
- "True is not of type 'array'"

### 5. Quick Verification Checklist

- [ ] List venues works
- [ ] List events works  
- [ ] Create venue works
- [ ] Create event works (with and without optional fields)
- [ ] Create event with topics (array parameter) works
- [ ] Calculate dates works (if AI plugin active, uses `ai/calculate-dates`)
- [ ] Update event works
- [ ] No PHP errors in debug log
- [ ] OpenAI accepts all function schemas (no validation errors)

### 6. Manual Schema Inspection (Optional)

If you want to verify the actual schemas being sent to OpenAI:

1. Add temporary logging in `get_gatherpress_functions()`:
```php
error_log('Function schema: ' . wp_json_encode($functions));
```

2. Check the debug log to see the actual schemas
3. Verify they match JSON Schema format

## Expected Behavior

After simplification:
- ✅ All abilities should work exactly as before
- ✅ Code is simpler and easier to maintain
- ✅ No conversion from flat format needed (v0.4.0 uses JSON Schema)
- ✅ Cleanup logic still handles edge cases (empty objects, invalid `required` fields, etc.)

## Rollback Plan

If issues are found:
1. The old `convert_flat_schema_to_json()` method can be restored
2. The conversion logic can handle both formats again
3. No data loss - just code complexity returns

