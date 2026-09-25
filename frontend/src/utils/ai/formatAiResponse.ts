/**
 * Formats the raw API response into a display-friendly structure for the chat UI.
 */
export function formatResponse(data: any): any {
  if (!data) return null

  // Ensure tool_results are properly parsed if they come as stringified JSON
  let toolResults = data.tool_results
  if (typeof toolResults === 'string') {
    try {
      toolResults = JSON.parse(toolResults)
    } catch {
      toolResults = []
    }
  }

  return {
    ...data,
    tool_results: toolResults || [],
  }
}
