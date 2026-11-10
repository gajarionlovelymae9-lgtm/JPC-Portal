import { apiRequest } from './api'
import type { ApiResponse } from '../types/api'
import type {
  EmployeePerformanceData,
  MonthlyRankItem,
  PerformanceInsight,
  PerformanceHistoryPoint,
  BadgeItem,
  MonthlyAchievementHistoryItem,
  PerformancePeriod,
} from '../types/performance'

export interface PerformanceListParams {
  year?: number
  month?: number
  department_id?: number
  rating?: string
  search?: string
  page?: number
  per_page?: number
}

interface PerformanceListData {
  period: PerformancePeriod
  data: {
    data: Array<any>
    current_page: number
    last_page: number
    total: number
  }
}

interface EmployeePerformanceDetail {
  record: any
  insights: PerformanceInsight
  history: PerformanceHistoryPoint[]
  badges: BadgeItem[]
}

interface RankingsData {
  period: PerformancePeriod
  total: number
  rankings: MonthlyRankItem[]
}

interface MyAchievementsData {
  total_badges: number
  employee_of_month_count: number
  badges: BadgeItem[]
  monthly_history: MonthlyAchievementHistoryItem[]
}

/** One badge entry of the /achievements/progress payload. */
export interface AchievementProgressItem {
  badge_id: number
  code: string
  name: string
  description: string | null
  icon: string | null
  category: string | null
  tier: string | null
  criteria_type: string | null
  earned: boolean
  award_dates: string[]
  progress: number | null
  criteria?: string | null
  current_value?: number
  target_value?: number
}

interface AchievementProgressData {
  stats: Record<string, number>
  badges: AchievementProgressItem[]
}

interface MonthlyAchievementsData {
  period: PerformancePeriod
  employee_of_the_month?: MonthlyAchievementHistoryItem
  rankings: MonthlyAchievementHistoryItem[]
}

export const performanceService = {
  /**
   * Fetch personal performance dashboard for authenticated employee.
   */
  async getMyPerformance(year?: number, month?: number): Promise<ApiResponse<EmployeePerformanceData>> {
    const params = new URLSearchParams()
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())

    return apiRequest<EmployeePerformanceData>(
      `/performance/me?${params.toString()}`,
      // Always re-read the authoritative snapshot when the employee refreshes.
      { cache: 'no-store' },
    )
  },

  /**
   * Fetch personal multi-month history trend.
   */
  async getMyHistory(): Promise<ApiResponse<PerformanceHistoryPoint[]>> {
    return apiRequest<PerformanceHistoryPoint[]>('/performance/me/history')
  },

  /**
   * Fetch paginated performance list for management overview.
   */
  async getPerformanceList(params: PerformanceListParams = {}): Promise<ApiResponse<PerformanceListData>> {
    const query = new URLSearchParams()
    if (params.year) query.append('year', params.year.toString())
    if (params.month) query.append('month', params.month.toString())
    if (params.department_id) query.append('department_id', params.department_id.toString())
    if (params.rating) query.append('rating', params.rating)
    if (params.search) query.append('search', params.search)
    if (params.page) query.append('page', params.page.toString())
    if (params.per_page) query.append('per_page', params.per_page.toString())

    return apiRequest<PerformanceListData>(`/performance?${query.toString()}`)
  },

  /**
   * Deep-dive employee performance details with AI advisory insights.
   */
  async getEmployeePerformance(employeeId: number, year?: number, month?: number): Promise<ApiResponse<EmployeePerformanceDetail>> {
    const params = new URLSearchParams()
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())

    return apiRequest<EmployeePerformanceDetail>(`/performance/employees/${employeeId}?${params.toString()}`)
  },

  /**
   * Fetch monthly leaderboard rankings and Employee of the Month.
   */
  async getRankings(year?: number, month?: number, departmentId?: number): Promise<ApiResponse<RankingsData>> {
    const query = new URLSearchParams()
    if (year) query.append('year', year.toString())
    if (month) query.append('month', month.toString())
    if (departmentId) query.append('department_id', departmentId.toString())

    return apiRequest<RankingsData>(`/performance/rankings?${query.toString()}`)
  },

  /**
   * Trigger authoritative recalculation.
   */
  async recalculatePerformance(data: { year: number; month: number; employee_id?: number; reason?: string }): Promise<ApiResponse<any>> {
    return apiRequest<any>('/performance/recalculate', {
      method: 'POST',
      body: data,
    })
  },

  /**
   * Catalog of all system badges.
   */
  async getBadgesCatalog(): Promise<ApiResponse<BadgeItem[]>> {
    return apiRequest<BadgeItem[]>('/achievements')
  },

  /**
   * Current user's badges and achievements.
   */
  async getMyAchievements(): Promise<ApiResponse<MyAchievementsData>> {
    return apiRequest<MyAchievementsData>('/achievements/me')
  },

  /**
   * Earned badges plus tracked progress toward every unearned badge.
   */
  async getMyAchievementProgress(): Promise<ApiResponse<AchievementProgressData>> {
    return apiRequest<AchievementProgressData>('/achievements/progress')
  },

  /**
   * Monthly achievements overview and Employee of the Month.
   */
  async getMonthlyAchievements(year?: number, month?: number): Promise<ApiResponse<MonthlyAchievementsData>> {
    const query = new URLSearchParams()
    if (year) query.append('year', year.toString())
    if (month) query.append('month', month.toString())

    return apiRequest<MonthlyAchievementsData>(`/achievements/monthly?${query.toString()}`)
  },
}
