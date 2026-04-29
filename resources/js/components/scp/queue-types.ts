export interface QueueNode {
    id: number;
    title: string;
    children?: QueueNode[];
}

export interface SavedSearch {
    id: number;
    title: string;
}

export interface QueueNavigation {
    queues: QueueNode[];
    personal: QueueNode[];
    savedSearches: SavedSearch[];
}
