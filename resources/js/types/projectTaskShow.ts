export type UserBrief = {
    id: number;
    name: string;
    email: string;
} | null;

export type TaskParentBrief = {
    id: number;
    title: string;
} | null;

export type SubtaskRow = {
    id: number;
    title: string;
    status: string;
    status_label: string;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    estimated_minutes: number | null;
    children_count: number;
    tree_depth: number;
    can_update: boolean;
    can_delete: boolean;
    is_assignee_only_limited: boolean;
    can_submit_task_completion: boolean;
    can_confirm_task_completion: boolean;
};

export type TaskDetail = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    assignee_user_id: number | null;
    assignee: UserBrief;
    project_requirement_id: number | null;
    requirement_title: string | null | undefined;
    parent_project_task_id: number | null;
    parent: TaskParentBrief;
    estimated_minutes: number | null;
    children_count: number;
    subtasks: SubtaskRow[];
    can_update: boolean;
    can_delete: boolean;
    completion_submitted_at: string | null;
    completion_submitted_by: UserBrief;
    is_assignee_only_limited: boolean;
    can_submit_task_completion: boolean;
    can_confirm_task_completion: boolean;
};

export type ProjectSummary = {
    id: number;
    name: string;
    code: string | null;
    estimation_required: boolean;
};

export type TimeEntryRow = {
    id: number;
    user_id: number;
    user_name: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    is_running: boolean;
    source: string;
    source_label: string;
    notes: string | null;
    can_update: boolean;
    can_delete: boolean;
};

export type TimeTracking = {
    can_track: boolean;
    totals: {
        my_today_seconds: number;
        my_all_time_seconds: number;
        task_all_time_seconds: number;
    };
    entries: TimeEntryRow[];
};

export type ChecklistItemRow = {
    id: number;
    title: string;
    is_completed: boolean;
};

export type Checklist = {
    can_manage: boolean;
    items: ChecklistItemRow[];
};

export type TaskShowPayload = {
    project: ProjectSummary;
    task: TaskDetail;
    can_manage_project: boolean;
    checklist: Checklist;
    time_tracking: TimeTracking;
};
