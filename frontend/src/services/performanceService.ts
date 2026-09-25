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
      `/api/v1/performance/me?${params.toString()}`
    )
  },

  /**
   * Fetch personal multi-month history trend.
   */
  async getMyHistory(): Promise<ApiResponse<PerformanceHistoryPoint[]>> {
    return apiRequest<PerformanceHistoryPoint[]>('/api/v1/performance/me/history')
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

    return apiRequest<PerformanceListData>(`/api/v1/performance?${query.toString()}`)
  },

  /**
   * Deep-dive employee performance details with AI advisory insights.
   */
  async getEmployeePerformance(employeeId: number, year?: number, month?: number): Promise<ApiResponse<EmployeePerformanceDetail>> {
    const params = new URLSearchParams()
    if (year) params.append('year', year.toString())
    if (month) params.append('month', month.toString())

    return apiRequest<EmployeePerformanceDetail>(`/api/v1/performance/employees/${employeeId}?${params.toString()}`)
  },

  /**
   * Fetch monthly leaderboard rankings and Employee of the Month.
   */
  async getRankings(year?: number, month?: number, departmentId?: number): Promise<ApiResponse<RankingsData>> {
    const query = new URLSearchParams()
    if (year) query.append('year', year.toString())
    if (month) query.append('month', month.toString())
    if (departmentId) query.append('department_id', departmentId.toString())

    return apiRequest<RankingsData>(`/api/v1/performance/rankings?${query.toString()}`)
  },

  /**
   * Trigger authoritative recalculation.
   */
  async recalculatePerformance(data: { year: number; month: number; employee_id?: number; reason?: string }): Promise<ApiResponse<any>> {
    return apiRequest<any>('/api/v1/performance/recalculate', {
      method: 'POST',
      body: JSON.stringify(data),
    })
  },

  /**
   * Catalog of all system badges.
   */
  async getBadgesCatalog(): Promise<ApiResponse<BadgeItem[]>> {
    return apiRequest<BadgeItem[]>('/api/v1/achievements')
  },

  /**
   * Current user's badges and achievements.
   */
  async getMyAchievements(): Promise<ApiResponse<MyAchievementsData>> {
    return apiRequest<MyAchievementsData>('/api/v1/achievements/me')
  },

  /**
   * Monthly achievements overview and Employee of the Month.
   */
  async getMonthlyAchievements(year?: number, month?: number): Promise<ApiResponse<MonthlyAchievementsData>> {
    const query = new URLSearchParams()
    if (year) query.append('year', year.toString())
    if (month) query.append('month', month.toString())

    return apiRequest<MonthlyAchievementsData>(`/api/v1/achievements/monthly?${query.toString()}`)
  },
}
