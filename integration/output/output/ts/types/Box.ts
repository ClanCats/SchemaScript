export interface Box {
  /** the position of the box */
  pos: {
    x: number;
    y: number;
  };
  /** the size of the box */
  size: {
    width: number;
    height: number;
  };
  /**
   * the rotation in radians
   * formula to convert from degrees to radians: degrees * (pi / 180)
   */
  rotation: number;
}
